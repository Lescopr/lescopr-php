<?php

declare(strict_types=1);

namespace Lescopr\Monitoring;

/**
 * Internal SDK logger — writes to .lescopr.log in the project root.
 * Mirrors Python's monitoring/logger.py.
 * Never sends its own output to the user's application log stream.
 */
class Logger
{
    private static ?string $logFile = null;
    private static bool    $debugMode = false;

    public static function setDebugMode(bool $enabled = true): void
    {
        self::$debugMode = $enabled;
    }

    public static function isDebugMode(): bool
    {
        return self::$debugMode;
    }

    public static function setLogFile(string $path): void
    {
        self::$logFile = $path;
    }

    public static function debug(string $message): void
    {
        if (self::$debugMode) {
            self::write('DEBUG', $message);
        }
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    public static function critical(string $message): void
    {
        self::write('CRITICAL', $message);
    }

    // ─────────────────── private ───────────────────

    private static function write(string $level, string $message): void
    {
        $file = self::resolveLogFile();
        if (!$file) {
            return;
        }

        $line = sprintf(
            "[%s] %s [lescopr] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function resolveLogFile(): ?string
    {
        if (self::$logFile) {
            return self::$logFile;
        }

        $candidates = [
            getcwd() . '/.lescopr.log',
            sys_get_temp_dir() . '/lescopr.log',
        ];

        foreach ($candidates as $path) {
            $dir = dirname($path);
            if (is_writable($dir)) {
                self::$logFile = $path;
                return $path;
            }
        }

        return null;
    }
}

