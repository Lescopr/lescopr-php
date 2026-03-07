<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Laravel\Logging;

use Lescopr\Core\Lescopr;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Monolog handler that forwards every log record to Lescopr.
 * Compatible with Monolog 1.x, 2.x, 3.x and PHP 7.4+.
 */
class LescoprMonologHandler extends AbstractProcessingHandler
{
    /** @var Lescopr */
    private $sdk;

    /** Loggers whose output we never forward (avoid infinite loops) */
    private const IGNORED_CHANNELS = ['lescopr', 'grpc', 'protobuf'];

    /**
     * @param int $level Monolog level constant (Logger::DEBUG etc.)
     */
    public function __construct(Lescopr $sdk, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->sdk = $sdk;
    }

    /**
     * Works with Monolog 1.x/2.x (array) and 3.x (LogRecord).
     *
     * @param array<string, mixed>|object $record
     */
    protected function write($record): void
    {
        try {
            // Monolog 3.x uses LogRecord object; 1.x/2.x use plain array
            if (is_object($record)) {
                $channel   = $record->channel  ?? 'app';           // @phpstan-ignore-line
                $levelName = method_exists($record->level, 'getName') // @phpstan-ignore-line
                    ? $record->level->getName()                        // @phpstan-ignore-line
                    : ($record->level ?? 'INFO');                      // @phpstan-ignore-line
                $message   = $record->message  ?? '';              // @phpstan-ignore-line
                $context   = (array) ($record->context  ?? []);    // @phpstan-ignore-line
                $datetime  = $record->datetime ?? new \DateTimeImmutable(); // @phpstan-ignore-line
            } else {
                $channel   = $record['channel']    ?? 'app';
                $levelName = $record['level_name'] ?? 'INFO';
                $message   = $record['message']    ?? '';
                $context   = $record['context']    ?? [];
                $datetime  = $record['datetime']   ?? new \DateTimeImmutable();
            }

            // Skip internal SDK logs
            foreach (self::IGNORED_CHANNELS as $ignored) {
                if (strpos(strtolower((string) $channel), $ignored) !== false) {
                    return;
                }
            }

            $timestamp = $datetime instanceof \DateTimeInterface
                ? $datetime->format(\DateTime::ATOM)
                : date(\DateTime::ATOM);

            $this->sdk->sendLog((string) $levelName, (string) $message, [
                'channel'   => $channel,
                'context'   => $context,
                'timestamp' => $timestamp,
                'source'    => 'laravel_monolog',
            ]);
        } catch (\Throwable $e) {
            // Never throw from a log handler
        }
    }
}

