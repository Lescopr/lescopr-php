<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Laravel;

use Illuminate\Support\ServiceProvider;
use Lescopr\Core\Lescopr;
use Lescopr\Integrations\Laravel\Logging\LescoprMonologHandler;

/**
 * Laravel Service Provider for Lescopr SDK.
 *
 * Auto-registered via composer.json extra.laravel.providers.
 *
 * Usage in config/logging.php:
 *   'channels' => [
 *       'lescopr' => [
 *           'driver' => 'monolog',
 *           'handler' => \Lescopr\Integrations\Laravel\Logging\LescoprMonologHandler::class,
 *       ],
 *       'stack' => [
 *           'driver' => 'stack',
 *           'channels' => ['single', 'lescopr'],
 *       ],
 *   ]
 *
 * Or simply add to your stack channels — see README.
 */
class LescoprServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Lescopr singleton
        $this->app->singleton(Lescopr::class, function () {
            $config = Lescopr::loadProjectConfig();

            if (empty($config)) {
                return null;
            }

            $instance = new Lescopr(
                $config['api_key']     ?? null,
                $config['sdk_key']     ?? null,
                $config['environment'] ?? (app()->environment() ?? 'development'),
                false,
                false
            );
            $instance->sdkId        = $config['sdk_id']       ?? null;
            $instance->projectName  = $config['project_name'] ?? null;
            $instance->projectStack = $config['project_stack'] ?? [];

            return $instance;
        });

        $this->app->alias(Lescopr::class, 'lescopr');
    }

    public function boot(): void
    {
        // Publish config stub
        $this->publishes([
            __DIR__ . '/config/lescopr.php' => config_path('lescopr.php'),
        ], 'lescopr-config');

        $this->mergeConfigFrom(__DIR__ . '/config/lescopr.php', 'lescopr');

        $this->bootLogging();
        $this->bootExceptionCapture();
    }

    private function bootLogging(): void
    {
        /** @var Lescopr|null $sdk */
        $sdk = $this->app->make(Lescopr::class);
        if (!$sdk || !$sdk->sdkId) {
            return;
        }

        // Auto-inject Monolog handler into the 'stack' channel if configured
        try {
            /** @var \Illuminate\Log\LogManager $logManager */
            $logManager = $this->app->make('log');
            $driver     = $logManager->getDefaultDriver();

            /** @var \Monolog\Logger $monolog */
            $monolog = $logManager->channel($driver)->getLogger();

            $handler = new LescoprMonologHandler($sdk);
            $monolog->pushHandler($handler);
        } catch (\Throwable $e) {
            // Logging not yet bootstrapped or Monolog not available
        }
    }

    private function bootExceptionCapture(): void
    {
        /** @var Lescopr|null $sdk */
        $sdk = $this->app->make(Lescopr::class);
        if (!$sdk || !$sdk->sdkId) {
            return;
        }

        // Capture unhandled exceptions via Laravel's exception handler
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        // Hook into the kernel exception event
        $this->app['events']->listen(
            \Illuminate\Foundation\Http\Events\RequestHandled::class,
            function () {}
        );

        // Listen to unhandled exceptions
        if (method_exists($this->app, 'make')) {
            try {
                /** @var \Illuminate\Foundation\Exceptions\Handler $handler */
                $originalHandler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
                $this->app->singleton(
                    \Illuminate\Contracts\Debug\ExceptionHandler::class,
                    function () use ($originalHandler, $sdk) {
                        return new LescoprExceptionHandler($originalHandler, $sdk);
                    }
                );
            } catch (\Throwable $e) {}
        }
    }
}

