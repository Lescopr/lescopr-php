<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Lescopr\Core\Lescopr;

/**
 * Decorates Laravel's exception handler to forward unhandled exceptions to Lescopr.
 */
class LescoprExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private readonly ExceptionHandler $inner,
        private readonly Lescopr $sdk
    ) {}

    public function report(\Throwable $e): void
    {
        try {
            $this->sdk->sendLog('ERROR', get_class($e) . ': ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => $e->getTraceAsString(),
                'source'         => 'laravel_exception_handler',
            ]);
        } catch (\Throwable) {}

        $this->inner->report($e);
    }

    public function shouldReport(\Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, \Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, \Throwable $e): void
    {
        $this->inner->renderForConsole($output, $e);
    }
}

