<?php

declare(strict_types=1);

namespace Lescopr\Core;

use Lescopr\Monitoring\Logger;
use Lescopr\Network\HttpClient;

/**
 * Background daemon runner.
 *
 * Spawned by pcntl_fork() in Lescopr::startDaemon().
 * Maintains a persistent loop that flushes the log queue to
 * api.lescopr.com every few seconds and sends heartbeats.
 */
class DaemonRunner
{
    private const FLUSH_INTERVAL     = 5;  // seconds
    private const HEARTBEAT_INTERVAL = 30; // seconds

    private bool $running = true;
    private Lescopr $client;
    private HttpClient $http;
    private int $lastHeartbeat = 0;

    public function run(): void
    {
        // Install signal handlers
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () { $this->running = false; });
            pcntl_signal(SIGINT,  function () { $this->running = false; });
        }

        $config = Lescopr::loadProjectConfig();
        if (empty($config)) {
            Logger::error('[DAEMON] No .lescopr.json found. Exiting.');
            return;
        }

        $this->client = new Lescopr(
            $config['api_key']     ?? null,
            $config['sdk_key']     ?? null,
            $config['environment'] ?? 'development',
            false,
            false
        );
        $this->client->sdkId       = $config['sdk_id']       ?? null;
        $this->client->projectName = $config['project_name'] ?? null;
        $this->client->projectStack = $config['project_stack'] ?? [];

        $this->http = new HttpClient();

        Logger::info('[DAEMON] Started (PID: ' . getmypid() . ')');

        $this->lastHeartbeat = time();
        $this->sendHeartbeat();

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $this->flushPendingLogs();

            if ((time() - $this->lastHeartbeat) >= self::HEARTBEAT_INTERVAL) {
                $this->sendHeartbeat();
                $this->lastHeartbeat = time();
            }

            sleep(self::FLUSH_INTERVAL);
        }

        // Final flush before exit
        $this->flushPendingLogs();
        Logger::info('[DAEMON] Shutdown complete.');
    }

    private function flushPendingLogs(): void
    {
        if (!$this->client->hasPendingLogs()) {
            return;
        }

        $logs = $this->client->getPendingLogs();
        if (empty($logs)) {
            return;
        }

        try {
            $response = $this->http->post(
                Lescopr::BASE_URL . '/sdk/logs/batch/',
                [
                    'sdk_id'      => $this->client->sdkId,
                    'environment' => $this->client->getEnvironment(),
                    'logs'        => $logs,
                ],
                [
                    'X-API-Key' => $this->client->getApiKey(),
                    'X-SDK-Key' => $this->client->getSdkKey(),
                ]
            );

            if (!($response['ok'] ?? false)) {
                Logger::warning('[DAEMON] Batch flush failed: ' . ($response['error'] ?? 'unknown'));
                // Re-queue logs
                foreach ($logs as $log) {
                    $this->client->addPendingLog($log);
                }
            } else {
                Logger::debug('[DAEMON] Flushed ' . count($logs) . ' logs');
            }
        } catch (\Throwable $e) {
            Logger::error('[DAEMON] Flush error: ' . $e->getMessage());
            // Re-queue logs
            foreach ($logs as $log) {
                $this->client->addPendingLog($log);
            }
        }
    }

    private function sendHeartbeat(): void
    {
        try {
            $this->http->post(
                Lescopr::BASE_URL . '/sdk/heartbeat/',
                [
                    'sdk_id'      => $this->client->sdkId,
                    'environment' => $this->client->getEnvironment(),
                    'daemon_pid'  => getmypid(),
                    'sdk_version' => Lescopr::SDK_VERSION,
                    'language'    => 'php',
                    'timestamp'   => (new \DateTime())->format(\DateTime::ATOM),
                ],
                [
                    'X-API-Key' => $this->client->getApiKey(),
                    'X-SDK-Key' => $this->client->getSdkKey(),
                ]
            );
        } catch (\Throwable $e) {
            Logger::debug('[DAEMON] Heartbeat error: ' . $e->getMessage());
        }
    }
}

