<?php

declare(strict_types=1);

namespace SonnaLabs\Lescopr;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class Lescopr
{
    private static bool $initialized = false;
    private static ?string $apiKey = null;
    private static ?string $instanceId = null;
    private static ?string $companyName = null;
    private static ?string $planStatus = null;
    private static ?string $planExpiry = null;
    private static bool $isAllowedToSend = false;
    private static ?WebSocketClient $wsClient = null;
    private static ?Api $apiClient = null;
    private static LoggerInterface $logger;
    private static Config $configHandler;

    private const WS_BASE_URI = 'wss://api.lescopr.com/v1/ws';

    private function __construct() {}

    /**
     * Initialize the Lescopr SDK.
     * @param array{apiKey?: string, instanceId?: string, configPath?: string, logger?: LoggerInterface} $options
     * @return bool
     */
    public static function init(array $options = []): bool
    {
        self::$logger = $options['logger'] ?? new NullLogger();

        if (self::$initialized) {
            self::$logger->warning('[Lescopr SDK] Already initialized.');
            return true;
        }

        self::$configHandler = new Config(self::$logger);
        self::$logger->info('[Lescopr SDK] Initializing...');

        $configPath = $options['configPath'] ?? getcwd() . DIRECTORY_SEPARATOR . Config::DEFAULT_FILENAME;
        $initError = null;

        try {
            $configFromFile = self::$configHandler->readConfig($configPath);

            self::$apiKey = $options['apiKey'] 
                ?? (($env = getenv('LESCOPR_API_KEY')) !== false ? (string)$env : null)
                ?? $configFromFile['apiKey'] ?? null;

            self::$instanceId = $options['instanceId']
                ?? (($env = getenv('LESCOPR_INSTANCE_ID')) !== false ? (string)$env : null)
                ?? $configFromFile['instanceId'] ?? null;

            if (!self::$apiKey || !self::$instanceId) {
                throw new LescoprException('API Key and Instance ID are required for initialization.');
            }

            self::$apiClient = new Api(self::$apiKey, self::$instanceId, null, self::$logger);
            $validationResult = self::$apiClient->validateApiKey();

            self::$companyName = $validationResult['companyName'] ?? null;
            self::$planStatus = $validationResult['planStatus'] ?? null;
            self::$planExpiry = $validationResult['planExpiry'] ?? null;
            self::$isAllowedToSend = in_array(self::$planStatus, ['active', 'trial']);

            $wsUrl = self::WS_BASE_URI . '?key=' . urlencode(self::$apiKey) . '&instance=' . urlencode(self::$instanceId);
            self::$wsClient = new WebSocketClient($wsUrl, self::$logger);
            
            if (self::$isAllowedToSend) {
                self::$wsClient->connect();
            }

            self::$initialized = true;
            self::$logger->info('[Lescopr SDK] Initialization successful.', [
                'instanceId' => self::$instanceId,
                'companyName' => self::$companyName,
                'planStatus' => self::$planStatus,
                'allowedToSend' => self::$isAllowedToSend,
            ]);
            return true;

        } catch (ApiException | ConfigException | LescoprException $e) {
            $initError = $e;
        } catch (\Throwable $e) {
            $initError = new LescoprException('An unexpected error occurred during initialization: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }

        self::$logger->error('[Lescopr SDK] Initialization failed: ' . $initError->getMessage(), ['exception' => $initError]);
        self::$initialized = false;
        self::resetSdkStateForTesting();
        return false;
    }

    /**
     * Send a log entry to Lescopr.
     * @param string|array<mixed>|\JsonSerializable $message
     * @param string $level
     */
    public static function sendLog(string|array|\JsonSerializable $message, string $level = 'info'): void
    {
        if (!self::canSend()) {
            return;
        }

        try {
            $payload = [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC1036),
                'level' => $level,
                'message' => is_string($message) ? $message : json_encode($message, JSON_THROW_ON_ERROR),
                'instanceId' => self::$instanceId,
            ];

            $wsPayload = json_encode(['type' => 'log', 'payload' => $payload], JSON_THROW_ON_ERROR);
            $success = self::$wsClient->send($wsPayload);
            
            if (!$success) {
                self::$logger->debug('[Lescopr SDK] WebSocket send failed, could implement HTTP fallback.');
            } else {
                self::$logger->debug('[Lescopr SDK] Sent log via WebSocket', ['payload_size' => strlen($wsPayload)]);
            }

        } catch (\JsonException $e) {
            self::$logger->error('[Lescopr SDK] Failed to encode log message.', ['exception' => $e]);
        } catch (\Throwable $e) {
            self::$logger->error('[Lescopr SDK] Failed to send log.', ['exception' => $e]);
        }
    }

    /**
     * Send a metric to Lescopr.
     * @param string $name
     * @param float|int $value
     * @param array<string, string|int|float|bool> $tags
     */
    public static function sendMetric(string $name, float|int $value, array $tags = []): void
    {
        if (!self::canSend()) {
            return;
        }

        try {
            $payload = [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
                'name' => $name,
                'value' => $value,
                'tags' => (object) $tags,
                'instanceId' => self::$instanceId,
            ];

            $wsPayload = json_encode(['type' => 'metric', 'payload' => $payload], JSON_THROW_ON_ERROR);
            $success = self::$wsClient->send($wsPayload);
            
            if (!$success) {
                self::$logger->debug('[Lescopr SDK] WebSocket send failed, could implement HTTP fallback.');
            } else {
                self::$logger->debug('[Lescopr SDK] Sent metric via WebSocket', [
                    'name' => $name,
                    'value' => $value,
                ]);
            }

        } catch (\JsonException $e) {
            self::$logger->error('[Lescopr SDK] Failed to encode metric data.', ['exception' => $e]);
        } catch (\Throwable $e) {
            self::$logger->error('[Lescopr SDK] Failed to send metric.', ['exception' => $e]);
        }
    }

    /**
     * Check if the SDK can send data
     */
    private static function canSend(): bool
    {
        if (!self::$initialized) {
            self::$logger->warning('[Lescopr SDK] SDK not initialized. Cannot send data.');
            return false;
        }
        if (!self::$isAllowedToSend) {
            self::$logger->debug('[Lescopr SDK] Sending is disabled based on plan status.');
            return false;
        }
        if (!self::$instanceId) {
            self::$logger->error('[Lescopr SDK] Instance ID is missing. Cannot send data.');
            return false;
        }
        if (!self::$wsClient || !self::$wsClient->isConnected()) {
            self::$logger->debug('[Lescopr SDK] WebSocket not connected. Attempting to reconnect...');
            try {
                return self::$wsClient && self::$wsClient->connect();
            } catch (\Throwable $e) {
                self::$logger->error('[Lescopr SDK] Failed to reconnect WebSocket.', ['exception' => $e]);
                return false;
            }
        }
        return true;
    }

    /**
     * Reset the internal state of the SDK
     * @internal
     */
    public static function resetSdkStateForTesting(): void
    {
        self::$initialized = false;
        self::$apiKey = null;
        self::$instanceId = null;
        self::$companyName = null;
        self::$planStatus = null;
        self::$planExpiry = null;
        self::$isAllowedToSend = false;
        if (self::$wsClient) {
            self::$wsClient->disconnect();
            self::$wsClient = null;
        }
        self::$apiClient = null;
        self::$logger = new NullLogger();
    }

    // --- Getters ---
    public static function isInitialized(): bool { return self::$initialized; }
    public static function getInstanceId(): ?string { return self::$instanceId; }
    public static function getPlanStatus(): ?string { return self::$planStatus; }
    public static function isAllowedToSend(): bool { return self::$isAllowedToSend; }
    public static function getCompanyName(): ?string { return self::$companyName; }
}

/**
 * Custom exception for Lescopr SDK related errors.
 */
class LescoprException extends \RuntimeException {}