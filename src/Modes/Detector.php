<?php

declare(strict_types=1);

namespace Lescopr\Modes;

/**
 * Auto-detect the optimal transport mode.
 *
 * Priority:
 *  1. LESCOPR_MODE env var (daemon | embedded | direct)
 *  2. Serverless / short-lived process signals   → direct
 *  3. PHP-FPM / mod_php (no persistent threads) → direct
 *  4. Long-running PHP (Swoole, ReactPHP, RoadRunner, CLI) → embedded
 *  5. Fallback                                  → direct
 *
 * Note: PHP has no daemon mode; "embedded" is a best-effort approach
 * for persistent PHP runtimes (Swoole etc.). In practice, most PHP
 * deployments will use "direct" (flush on shutdown via register_shutdown_function).
 */
class Detector
{
    /**
     * @return 'direct'|'embedded'
     */
    public static function detect(): string
    {
        $forced = strtolower((string) getenv('LESCOPR_MODE'));
        if (in_array($forced, ['direct', 'embedded'], true)) {
            return $forced;
        }

        if (self::isServerless()) {
            return 'direct';
        }

        if (self::isFpmOrModPhp()) {
            return 'direct';
        }

        if (self::isPersistentRuntime()) {
            return 'embedded';
        }

        // Default: PHP lives per-request, direct is always safe
        return 'direct';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function isServerless(): bool
    {
        $signals = [
            'AWS_LAMBDA_FUNCTION_NAME',
            'AWS_LAMBDA_RUNTIME_API',
            'LAMBDA_TASK_ROOT',
            'VERCEL',
            'VERCEL_ENV',
            'K_SERVICE',                    // Google Cloud Run
            'FUNCTION_NAME',               // Google Cloud Functions
            'FUNCTIONS_WORKER_RUNTIME',    // Azure Functions
            'AZURE_FUNCTIONS_ENVIRONMENT',
        ];

        foreach ($signals as $signal) {
            if (getenv($signal) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function isFpmOrModPhp(): bool
    {
        $sapi = PHP_SAPI;

        // fpm-fcgi  → PHP-FPM
        // apache2handler, litespeed, nsapi, etc. → embedded in web server
        $webSapis = ['fpm-fcgi', 'apache2handler', 'litespeed', 'nsapi', 'embed'];
        if (in_array($sapi, $webSapis, true)) {
            return true;
        }

        return false;
    }

    private static function isPersistentRuntime(): bool
    {
        // Swoole coroutine context
        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            return true;
        }

        // RoadRunner — exposes a specific env var
        if (getenv('RR_MODE') !== false) {
            return true;
        }

        // ReactPHP / Amp long-running CLI — just "cli" SAPI without web context
        if (PHP_SAPI === 'cli' && getenv('LESCOPR_PERSISTENT') !== false) {
            return true;
        }

        return false;
    }
}
