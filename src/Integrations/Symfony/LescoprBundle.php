+++<?php


declare(strict_types=1);

namespace Lescopr\Integrations\Symfony;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Symfony Bundle for Lescopr SDK.
 *
 * Registration in config/bundles.php:
 *   Lescopr\Integrations\Symfony\LescoprBundle::class => ['all' => true]
 */
class LescoprBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder
    ): void {
        // Register the Monolog handler as a service
        $container->services()
            ->set('lescopr.monolog_handler', LescoprMonologHandler::class)
                ->args(['%lescopr.sdk_instance%'])
                ->tag('monolog.handler');

        // Register the exception subscriber
        $container->services()
            ->set('lescopr.exception_subscriber', EventSubscriber\KernelExceptionSubscriber::class)
                ->tag('kernel.event_subscriber')
                ->autowire()
                ->autoconfigure();
    }

    public function configure(ContainerConfigurator $container): void
    {
        $container->extension('monolog', [
            'channels' => ['lescopr'],
        ]);
    }
}

