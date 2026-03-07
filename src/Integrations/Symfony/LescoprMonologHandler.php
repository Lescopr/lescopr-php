<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Symfony;

use Lescopr\Core\Lescopr;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Monolog handler for Symfony.
 * Compatible with Monolog 1.x, 2.x, 3.x and PHP 7.4+.
 */
class LescoprMonologHandler extends AbstractProcessingHandler
{
    private const IGNORED_CHANNELS = ['lescopr', 'security'];

    /** @var Lescopr */
    private $sdk;

    public function __construct(Lescopr $sdk, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->sdk = $sdk;
    }

    /**
     * @param array<string, mixed>|object $record
     */
    protected function write($record): void
    {
        try {
            if (is_object($record)) {
                $channel   = $record->channel  ?? 'app';           // @phpstan-ignore-line
                $levelName = method_exists($record->level ?? null, 'getName') // @phpstan-ignore-line
                    ? $record->level->getName()                     // @phpstan-ignore-line
                    : ($record->level ?? 'INFO');                   // @phpstan-ignore-line
                $message   = $record->message  ?? '';              // @phpstan-ignore-line
                $context   = (array) ($record->context ?? []);     // @phpstan-ignore-line
                $datetime  = $record->datetime ?? new \DateTimeImmutable(); // @phpstan-ignore-line
            } else {
                $channel   = $record['channel']    ?? 'app';
                $levelName = $record['level_name'] ?? 'INFO';
                $message   = $record['message']    ?? '';
                $context   = $record['context']    ?? [];
                $datetime  = $record['datetime']   ?? new \DateTimeImmutable();
            }

            foreach (self::IGNORED_CHANNELS as $ignored) {
                if (strpos(strtolower((string) $channel), $ignored) !== false) {
                    return;
                }
            }

            $this->sdk->sendLog((string) $levelName, (string) $message, [
                'channel'   => $channel,
                'context'   => $context,
                'timestamp' => $datetime instanceof \DateTimeInterface
                    ? $datetime->format(\DateTime::ATOM)
                    : date(\DateTime::ATOM),
                'source' => 'symfony_monolog',
            ]);
        } catch (\Throwable $e) {}
    }
}
