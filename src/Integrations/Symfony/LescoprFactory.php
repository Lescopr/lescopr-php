<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Symfony;

use Lescopr\Core\Lescopr;

/**
 * Factory helper for creating a Lescopr SDK instance from .lescopr.json.
 * Used by Symfony's DI container via the 'factory' service configuration.
 *
 * config/services.yaml:
 *   Lescopr\Core\Lescopr:
 *     factory: ['Lescopr\Integrations\Symfony\LescoprFactory', 'create']
 *     public: true
 */
class LescoprFactory
{
    public static function create(): ?Lescopr
    {
        $config = Lescopr::loadProjectConfig();

        if (empty($config)) {
            return null;
        }

        $instance = new Lescopr(
            $config['api_key']     ?? null,
            $config['sdk_key']     ?? null,
            $config['environment'] ?? 'development',
            false,
            false
        );

        $instance->sdkId        = $config['sdk_id']       ?? null;
        $instance->projectName  = $config['project_name'] ?? null;
        $instance->projectStack = $config['project_stack'] ?? [];

        return $instance;
    }
}

