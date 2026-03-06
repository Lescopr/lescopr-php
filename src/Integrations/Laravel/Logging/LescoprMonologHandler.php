<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Laravel\Logging;

use Lescopr\Core\Lescopr;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that forwards every log record to Lescopr.
 *
 * Works with both Monolog 2.x (array $record) and 3.x (LogRecord $record).
 */
class LescoprMonologHandler extends AbstractProcessingHandler
{
    private Lescopr $sdk;

    /** Loggers whose output we never forward (avoid infinite loops) */
    private const IGNORED_CHANNELS = ['lescopr', 'grpc', 'protobuf'];

    public function __construct(Lescopr $sdk, int|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->sdk = $sdk;
    }

    /**
     * @param array<string, mixed>|LogRecord $record
     */
    protected function write(array|LogRecord $record): void
    {
        try {
            // Monolog 3.x uses LogRecord, 2.x uses array
            if ($record instanceof LogRecord) {
                $channel   = $record->channel;
                $levelName = $record->level->getName();
                $message   = $record->message;
                $context   = $record->context;
                $datetime  = $record->datetime;
                $extra     = $record->extra;
            } else {
                $channel   = $record['channel']  ?? 'app';
                $levelName = $record['level_name'] ?? 'INFO';
                $message   = $record['message']   ?? '';
                $context   = $record['context']   ?? [];
                $datetime  = $record['datetime']  ?? new \DateTimeImmutable();
                $extra     = $record['extra']      ?? [];
            }

            // Skip internal SDK logs
            foreach (self::IGNORED_CHANNELS as $ignored) {
                if (str_contains(strtolower($channel), $ignored)) {
                    return;
                }
            }

            $metadata = [
                'channel'   => $channel,
                'context'   => $context,
                'extra'     => $extra,
                'timestamp' => $datetime instanceof \DateTimeInterface
                    ? $datetime->format(\DateTime::ATOM)
                    : date(\DateTime::ATOM),
                'source'    => 'laravel_monolog',
            ];

            $this->sdk->sendLog($levelName, $message, $metadata);
        } catch (\Throwable) {
            // Never throw from a log handler
        }
    }
}

