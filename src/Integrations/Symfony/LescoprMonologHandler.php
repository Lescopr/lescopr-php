<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Symfony;

use Lescopr\Core\Lescopr;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler for Symfony — identical to the Laravel one but lives
 * in the Symfony namespace so it can be wired via services.yaml independently.
 *
 * config/packages/monolog.yaml:
 *   monolog:
 *     handlers:
 *       lescopr:
 *         type: service
 *         id: Lescopr\Integrations\Symfony\LescoprMonologHandler
 */
class LescoprMonologHandler extends AbstractProcessingHandler
{
    private const IGNORED_CHANNELS = ['lescopr', 'security'];

    public function __construct(
        private readonly Lescopr $sdk,
        int|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(array|LogRecord $record): void
    {
        try {
            if ($record instanceof LogRecord) {
                $channel   = $record->channel;
                $levelName = $record->level->getName();
                $message   = $record->message;
                $context   = $record->context;
                $datetime  = $record->datetime;
            } else {
                $channel   = $record['channel']    ?? 'app';
                $levelName = $record['level_name'] ?? 'INFO';
                $message   = $record['message']    ?? '';
                $context   = $record['context']    ?? [];
                $datetime  = $record['datetime']   ?? new \DateTimeImmutable();
            }

            foreach (self::IGNORED_CHANNELS as $ignored) {
                if (str_contains(strtolower($channel), $ignored)) {
                    return;
                }
            }

            $this->sdk->sendLog($levelName, $message, [
                'channel'   => $channel,
                'context'   => $context,
                'timestamp' => $datetime instanceof \DateTimeInterface
                    ? $datetime->format(\DateTime::ATOM)
                    : date(\DateTime::ATOM),
                'source' => 'symfony_monolog',
            ]);
        } catch (\Throwable) {}
    }
}

