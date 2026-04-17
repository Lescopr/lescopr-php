<?php

declare(strict_types=1);

namespace Lescopr\Core;

use Lescopr\Filesystem\ConfigManager;
use Lescopr\Filesystem\Analyzers\ProjectAnalyzer;
use Lescopr\Network\HttpClient;
use Lescopr\Monitoring\Logger;
use Lescopr\Modes\Detector;
use Lescopr\Modes\DirectMode;

/**
 * Lescopr Core SDK - Central management object
 *
 * Handles configuration, log queue, daemon lifecycle,
 * and HTTP communication with api.lescopr.com.
 */
class Lescopr
{
    public const BASE_URL    = 'https://api.lescopr.com/api/v1';
    public const SDK_VERSION = '1.0.2';
    public const VALID_ENVIRONMENTS = ['development', 'production'];

    private ?string $apiKey;
    private ?string $sdkKey;
    private string  $environment;

    public ?string $sdkId       = null;
    public ?string $projectName = null;
    public array   $projectStack = [];

    private ConfigManager $configManager;
    private ?HttpClient   $httpClient  = null;

    /** @var array<int, array<string, mixed>> */
    private array $pendingLogs = [];
    private int   $maxPendingLogs = 1000;

    private bool $autoLogging;
    private bool $autoHttp;

    /** @var 'direct'|'embedded'|null */
    private ?string $mode = null;
    private ?DirectMode $directMode = null;

    public function __construct(
        ?string $apiKey      = null,
        ?string $sdkKey      = null,
        string  $environment = 'development',
        bool    $autoLogging = true,
        bool    $autoHttp    = true
    ) {
        $this->apiKey      = $apiKey;
        $this->sdkKey      = $sdkKey;
        $this->environment = $environment;
        $this->autoLogging = $autoLogging;
        $this->autoHttp    = $autoHttp;
        $this->configManager = new ConfigManager();

        if (!$apiKey || !$sdkKey) {
            $this->loadConfig();
        }

        if ($autoLogging && $autoHttp && $this->sdkId) {
            $this->setupAutoLogging();
        }
    }

    // -------------------------------------------------------------------------
    // Zero-config bootstrap (mirrors Python lescopr.logs())
    // -------------------------------------------------------------------------

    /**
     * Zero-config init — load .lescopr/config.json and auto-detect mode.
     *
     * Usage (e.g. top of index.php or bootstrap.php):
     *   \Lescopr\Core\Lescopr::logs();
     *
     * @return self|null
     */
    public static function logs(): ?self
    {
        $config = static::loadProjectConfig();
        if (empty($config) || empty($config['sdk_key'])) {
            return null;
        }

        $instance = new static(
            $config['api_key']      ?? null,
            $config['sdk_key']      ?? null,
            $config['environment']  ?? 'development',
            false,
            false
        );
        $instance->sdkId        = $config['sdk_id']        ?? null;
        $instance->projectName  = $config['project_name']  ?? null;
        $instance->projectStack = $config['project_stack'] ?? [];

        $mode = Detector::detect();
        $instance->mode = $mode;
        Logger::info("[LESCOPR] Mode détecté: {$mode}");

        if ($mode === 'direct' || $mode === 'embedded') {
            $baseUrl = (string) (getenv('LESCOPR_API_URL') ?: static::BASE_URL);
            $instance->directMode = new DirectMode(
                $config['sdk_key'],
                $config['api_key'] ?? '',
                $baseUrl
            );
            $instance->directMode->start();
        }

        return $instance;
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    private function loadConfig(): void
    {
        try {
            $config = $this->configManager->load();
            if (empty($config)) {
                return;
            }
            $this->apiKey      = $this->apiKey      ?? ($config['api_key']      ?? null);
            $this->sdkKey      = $this->sdkKey      ?? ($config['sdk_key']      ?? null);
            $this->environment = $this->environment ?? ($config['environment']  ?? 'development');
            $this->sdkId       = $config['sdk_id']      ?? null;
            $this->projectName = $config['project_name'] ?? null;
            $this->projectStack = $config['project_stack'] ?? [];
        } catch (\Throwable $e) {
            Logger::warning('[LESCOPR] Config load error: ' . $e->getMessage());
        }
    }

    public static function loadProjectConfig(): array
    {
        $configFile = getcwd() . '/.lescopr.json';
        if (!file_exists($configFile)) {
            return [];
        }
        try {
            return json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function saveProjectConfig(array $config): bool
    {
        $configFile = getcwd() . '/.lescopr.json';
        try {
            file_put_contents(
                $configFile,
                json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Initialisation (called by CLI `init`)
    // -------------------------------------------------------------------------

    public function initialize(): array
    {
        $this->validateConfig();

        $analyzer       = new ProjectAnalyzer();
        $projectAnalysis = $analyzer->analyze();

        if (empty($projectAnalysis)) {
            throw new \RuntimeException('Project analysis failed');
        }

        $client   = new HttpClient();
        $response = $client->post(
            self::BASE_URL . '/sdk/verify/',
            $projectAnalysis,
            [
                'X-API-Key' => $this->apiKey,
                'X-SDK-Key' => $this->sdkKey,
            ]
        );

        if (!$response['ok']) {
            throw new \RuntimeException('SDK verification failed: ' . ($response['error'] ?? 'Unknown error'));
        }

        $data             = $response['data']['data'] ?? $response['data'] ?? [];
        $this->sdkId      = $data['id'] ?? null;
        $this->projectName = $projectAnalysis['project_name'] ?? 'unknown';
        $this->projectStack = array_column($projectAnalysis['detected_frameworks'] ?? [], 'name');

        if (!$this->sdkId) {
            throw new \RuntimeException('SDK ID not received from server');
        }

        $config = [
            'sdk_id'        => $this->sdkId,
            'sdk_key'       => $this->sdkKey,
            'api_key'       => $this->apiKey,
            'environment'   => $this->environment,
            'project_name'  => $this->projectName,
            'project_stack' => $this->projectStack,
        ];

        self::saveProjectConfig($config);
        return $config;
    }

    private function validateConfig(): void
    {
        if (!$this->apiKey) {
            throw new \InvalidArgumentException('API key is required');
        }
        if (!in_array($this->environment, self::VALID_ENVIRONMENTS, true)) {
            throw new \InvalidArgumentException('Invalid environment: ' . $this->environment);
        }
    }

    // -------------------------------------------------------------------------
    // Static project lifecycle helpers
    // -------------------------------------------------------------------------

    public static function initializeProject(string $apiKey, string $sdkKey, string $environment = 'development'): array
    {
        try {
            $instance = new self($apiKey, $sdkKey, $environment, false, false);
            $config   = $instance->initialize();
            return ['success' => true, 'config' => $config, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'config' => null, 'error' => $e->getMessage()];
        }
    }

    public static function startDaemon(): array
    {
        $files = self::getProjectFiles();

        if (!file_exists($files['config'])) {
            return ['success' => false, 'error' => 'Project not initialised. Run lescopr init first.'];
        }

        // Check existing daemon
        if (file_exists($files['pid'])) {
            $pid = (int) trim(file_get_contents($files['pid']));
            if ($pid > 0 && posix_kill($pid, 0)) {
                return ['success' => false, 'error' => "Daemon already running (PID: $pid)"];
            }
            unlink($files['pid']);
        }

        if (!function_exists('pcntl_fork')) {
            return ['success' => false, 'error' => 'pcntl extension required for daemon mode. Install ext-pcntl.'];
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            return ['success' => false, 'error' => 'Failed to fork process'];
        }

        if ($pid > 0) {
            // Parent – write PID and return
            file_put_contents($files['pid'], (string) $pid);
            usleep(500000); // Wait 0.5s for daemon to start
            return ['success' => true, 'pid' => $pid, 'log_file' => $files['log'], 'error' => null];
        }

        // Child – become daemon
        if (function_exists('posix_setsid')) {
            posix_setsid();
        }

        // Redirect stdout/stderr
        fclose(STDIN);
        $logHandle = fopen($files['log'], 'a');

        putenv('LESCOPR_DAEMON_MODE=true');

        $daemon = new DaemonRunner();
        $daemon->run();

        exit(0);
    }

    public static function stopDaemon(): array
    {
        $files = self::getProjectFiles();

        if (!file_exists($files['pid'])) {
            return ['success' => false, 'error' => 'No running daemon found'];
        }

        $pid = (int) trim(file_get_contents($files['pid']));

        if (!posix_kill($pid, SIGTERM)) {
            @unlink($files['pid']);
            return ['success' => false, 'error' => 'Failed to send SIGTERM to daemon'];
        }

        // Wait up to 5 seconds
        for ($i = 0; $i < 5; $i++) {
            if (!posix_kill($pid, 0)) {
                break;
            }
            sleep(1);
        }

        // Force kill if needed
        if (posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
        }

        @unlink($files['pid']);
        return ['success' => true, 'error' => null];
    }

    public static function getStatus(): array
    {
        $files         = self::getProjectFiles();
        $config        = self::loadProjectConfig();
        $daemonRunning = false;
        $daemonPid     = null;

        if (file_exists($files['pid'])) {
            $pid = (int) trim(file_get_contents($files['pid']));
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                $daemonRunning = true;
                $daemonPid     = $pid;
            }
        }

        return [
            'project_path'   => getcwd(),
            'config_file'    => $files['config'],
            'config_exists'  => file_exists($files['config']),
            'config'         => $config,
            'daemon_running' => $daemonRunning,
            'daemon_pid'     => $daemonPid,
            'log_file'       => $files['log'],
        ];
    }

    public static function resetProject(bool $keepConfig = false): array
    {
        $files  = self::getProjectFiles();
        $result = [
            'success'           => true,
            'daemon_stopped'    => false,
            'config_removed'    => false,
            'logs_cleaned'      => false,
        ];

        // Stop daemon
        if (file_exists($files['pid'])) {
            $stopResult = self::stopDaemon();
            $result['daemon_stopped'] = $stopResult['success'];
        }

        // Remove config
        if (!$keepConfig && file_exists($files['config'])) {
            @unlink($files['config']);
            $result['config_removed'] = true;
        }

        // Remove logs
        foreach ([$files['log']] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $result['logs_cleaned'] = true;

        return $result;
    }

    // -------------------------------------------------------------------------
    // HTTP client
    // -------------------------------------------------------------------------

    public function getHttpClient(): HttpClient
    {
        if (!$this->httpClient) {
            $this->httpClient = new HttpClient();
        }
        return $this->httpClient;
    }

    // -------------------------------------------------------------------------
    // Log queue (central, thread-safe via file locking in PHP context)
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $logData
     */
    public function addPendingLog(array $logData): void
    {
        $this->pendingLogs[] = $logData;

        if (count($this->pendingLogs) > $this->maxPendingLogs) {
            $this->pendingLogs = array_slice($this->pendingLogs, -500);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPendingLogs(): array
    {
        $logs = $this->pendingLogs;
        $this->pendingLogs = [];
        return $logs;
    }

    public function hasPendingLogs(): bool
    {
        return !empty($this->pendingLogs);
    }

    // -------------------------------------------------------------------------
    // Send log
    // -------------------------------------------------------------------------

    public function sendLog(string $level, string $message, array $metadata = []): bool
    {
        // Direct / embedded mode: queue in DirectMode, flush on shutdown
        if ($this->directMode !== null) {
            $this->directMode->addLog([
                'level'       => strtolower($level),
                'message'     => $message,
                'context'     => $metadata,
                'source'      => $metadata['source'] ?? 'unknown',
                'logger_name' => $metadata['logger'] ?? 'unknown',
            ]);
            return true;
        }

        // Legacy HTTP send (daemon mode / manual usage)
        $client = $this->getHttpClient();
        try {
            $response = $client->post(
                self::BASE_URL . '/sdk/logs/',
                [
                    'sdk_id'      => $this->sdkId,
                    'environment' => $this->environment,
                    'level'       => $level,
                    'message'     => $message,
                    'metadata'    => $metadata,
                    'timestamp'   => (new \DateTime())->format(\DateTime::ATOM),
                ],
                [
                    'X-API-Key' => $this->apiKey,
                    'X-SDK-Key' => $this->sdkKey,
                ]
            );
            return $response['ok'] ?? false;
        } catch (\Throwable $e) {
            $this->addPendingLog([
                'level'     => $level,
                'message'   => $message,
                'metadata'  => $metadata,
                'timestamp' => time(),
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Auto logging bootstrap
    // -------------------------------------------------------------------------

    private function setupAutoLogging(): void
    {
        // Implemented via framework integrations
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function getProjectFiles(): array
    {
        $root = getcwd();
        return [
            'config' => $root . '/.lescopr.json',
            'log'    => $root . '/.lescopr.log',
            'pid'    => $root . '/.lescopr.pid',
        ];
    }

    public function getApiKey(): ?string    { return $this->apiKey; }
    public function getSdkKey(): ?string    { return $this->sdkKey; }
    public function getEnvironment(): string { return $this->environment; }
}

