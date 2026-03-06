<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Symfony\EventSubscriber;

use Lescopr\Core\Lescopr;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Captures Symfony kernel exceptions and forwards them to Lescopr.
 *
 * Registered automatically via the LescoprBundle DI configuration.
 * Can also be registered manually:
 *
 *   # config/services.yaml
 *   Lescopr\Integrations\Symfony\EventSubscriber\KernelExceptionSubscriber:
 *       arguments: ['@lescopr.sdk']
 *       tags:
 *           - { name: kernel.event_subscriber }
 */
class KernelExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Lescopr $sdk) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        try {
            $this->sdk->sendLog(
                'ERROR',
                get_class($exception) . ': ' . $exception->getMessage(),
                [
                    'exception_type' => get_class($exception),
                    'file'           => $exception->getFile(),
                    'line'           => $exception->getLine(),
                    'trace'          => $exception->getTraceAsString(),
                    'request_uri'    => $event->getRequest()->getRequestUri(),
                    'request_method' => $event->getRequest()->getMethod(),
                    'source'         => 'symfony_kernel_exception',
                ]
            );
        } catch (\Throwable) {
            // Never throw from an event subscriber
        }
    }
}

