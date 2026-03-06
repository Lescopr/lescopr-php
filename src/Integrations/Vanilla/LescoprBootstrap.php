<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Vanilla;

use Lescopr\Core\Lescopr;
use Lescopr\Monitoring\Logger;

/**
 * Zero-dependency bootstrap for vanilla PHP / custom OOP projects.
 *
 * Usage — add ONE line at the very top of your entry point (index.php):
 *
 *   require 'vendor/autoload.php';
 *   \Lescopr\Integrations\Vanilla\LescoprBootstrap::init();
 *
 * The bootstrap:
 *  - Reads .lescopr.json from the project root
 *  - Installs set_error_handler / set_exception_handler / register_shutdown_function
 *  - Buffers all PHP errors and forwards them to Lescopr
 */
class LescoprBootstrap
{
    private static ?self $instance = null;
    private Lescopr $sdk;

    /** Map PHP error constants to log level strings */
    private const ERROR_LEVEL_MAP = [
        E_ERROR             => 'CRITICAL',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'CRITICAL',
        E_NOTICE            => 'INFO',
        E_CORE_ERROR        => 'CRITICAL',
        E_COMPILE_ERROR     => 'CRITICAL',
        E_USER_ERROR        => 'ERROR',
        E_USER_WARNING      => 'WARNING',
        E_USER_NOTICE       => 'INFO',
        E_STRICT            => 'DEBUG',
        E_RECOVERABLE_ERROR => 'ERROR',
        E_DEPRECATED        => 'WARNING',
        E_USER_DEPRECATED   => 'WARNING',
    ];

    private function __construct(Lescopr $sdk)
    {
        $this->sdk = $sdk;
    }

    /**
     * Bootstrap Lescopr for a vanilla PHP project.
     *
     * @param array<string, mixed> $options  Optional overrides:
     *   'sdk_key'     => string
     *   'api_key'     => string
     *   'environment' => string
     *   'debug'       => bool
     */
    public static function init(array $options = []): ?self
    {
        if (self::$instance) {
            return self::$instance;
        }

        // Don't activate inside the SDK daemon
        if (getenv('LESCOPR_DAEMON_MODE') === 'true') {
            return null;
        }

        $config = Lescopr::loadProjectConfig();

        // Merge explicit options
        foreach (['sdk_key', 'api_key', 'environment'] as $key) {
            if (isset($options[$key])) {
                $config[$key] = $options[$key];
            }
        }

        if (empty($config['sdk_id']) && empty($config['sdk_key'])) {
            // No config found — silently skip
            return null;
        }

        if (!empty($options['debug'])) {
            Logger::setDebugMode(true);
        }

        $sdk = new Lescopr(
            $config['api_key']     ?? null,
            $config['sdk_key']     ?? null,
            $config['environment'] ?? 'development',
            false,
            false
        );
        $sdk->sdkId        = $config['sdk_id']       ?? null;
        $sdk->projectName  = $config['project_name'] ?? null;
        $sdk->projectStack = $config['project_stack'] ?? [];

        self::$instance = new self($sdk);
        self::$instance->installHandlers();

        Logger::debug('[BOOTSTRAP] Lescopr vanilla bootstrap activated');

        return self::$instance;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function getSdk(): Lescopr
    {
        return $this->sdk;
    }

    // ─────────────────── handler installation ───────────────────

    private function installHandlers(): void
    {
        $this->installErrorHandler();
        $this->installExceptionHandler();
        $this->installShutdownHandler();
    }

    private function installErrorHandler(): void
    {
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            // Respect the @ error-suppression operator
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $level = self::ERROR_LEVEL_MAP[$errno] ?? 'ERROR';

            try {
                $this->sdk->sendLog($level, $errstr, [
                    'error_no'  => $errno,
                    'file'      => $errfile,
                    'line'      => $errline,
                    'source'    => 'php_error_handler',
                ]);
            } catch (\Throwable) {}

            // Return false to let the standard PHP error handler continue
            return false;
        });
    }

    private function installExceptionHandler(): void
    {
        set_exception_handler(function (\Throwable $exception): void {
            try {
                $this->sdk->sendLog('CRITICAL', get_class($exception) . ': ' . $exception->getMessage(), [
                    'exception_type' => get_class($exception),
                    'file'           => $exception->getFile(),
                    'line'           => $exception->getLine(),
                    'trace'          => $exception->getTraceAsString(),
                    'source'         => 'php_exception_handler',
                ]);
            } catch (\Throwable) {}
        });
    }

    private function installShutdownHandler(): void
    {
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                try {
                    $this->sdk->sendLog('CRITICAL', 'Fatal error: ' . $error['message'], [
                        'error_type' => $error['type'],
                        'file'       => $error['file'],
                        'line'       => $error['line'],
                        'source'     => 'php_shutdown_handler',
                    ]);
                } catch (\Throwable) {}
            }
        });
    }
}

