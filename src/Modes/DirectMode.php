<?php

declare(strict_types=1);

namespace Lescopr\Modes;

use Lescopr\Network\HttpClient;
use Lescopr\Monitoring\Logger;

/**
 * DirectMode — in-memory log buffer + HTTPS batch flush to /logs/ingest.
 *
 * Designed for PHP-FPM and other short-lived PHP processes:
 *  - Logs are buffered in memory during the request
 *  - Flushed on process shutdown via register_shutdown_function
 *  - Also flushable manually via flush()
 *
 * Thread safety: PHP-FPM is process-per-request, so no locking needed.
 * For Swoole coroutines, the caller is responsible for per-coroutine isolation.
 */
class DirectMode
{
    private const BATCH_SIZE = 100;
    private const MAX_QUEUE  = 500;

    private array      $queue = [];
    private HttpClient $http;
    private string     $sdkKey;
    private string     $apiKey;
    private string     $baseUrl;
    private bool       $shutdownRegistered = false;

    public function __construct(
        string $sdkKey,
        string $apiKey,
        string $baseUrl = 'https://api.lescopr.com/api/v1'
    ) {
        $this->sdkKey  = $sdkKey;
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http    = new HttpClient();
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function start(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }
        // Guaranteed flush when PHP process ends (request or CLI)
        register_shutdown_function([$this, 'flush']);
        $this->shutdownRegistered = true;

        Logger::info('[DIRECT] Mode direct actif (HTTPS batch)');
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function addLog(array $entry): void
    {
        $this->queue[] = $entry;

        if (count($this->queue) > self::MAX_QUEUE) {
            // Keep the most recent half
            $this->queue = array_slice($this->queue, -intdiv(self::MAX_QUEUE, 2));
        }
    }

    public function flush(): void
    {
        if (empty($this->queue)) {
            return;
        }

        while (!empty($this->queue)) {
            $batch       = array_splice($this->queue, 0, self::BATCH_SIZE);
            $this->sendBatch($batch);
        }
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function sendBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $url = $this->baseUrl . '/logs/ingest';

        $result = $this->http->post(
            $url,
            ['logs' => $batch],
            [
                'X-SDK-Key' => $this->sdkKey,
                'X-API-Key' => $this->apiKey,
            ]
        );

        if ($result['ok']) {
            Logger::debug(sprintf('[DIRECT] %d log(s) envoyé(s)', count($batch)));
        } else {
            Logger::warning(sprintf(
                '[DIRECT] Flush échoué HTTP %d — %s',
                $result['status'],
                $result['error'] ?? 'unknown'
            ));
            // Re-queue on error (prepend so they are retried first)
            $this->queue = array_merge($batch, $this->queue);
            if (count($this->queue) > self::MAX_QUEUE) {
                $this->queue = array_slice($this->queue, -intdiv(self::MAX_QUEUE, 2));
            }
        }
    }
}
