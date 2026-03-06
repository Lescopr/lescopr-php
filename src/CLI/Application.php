<?php

declare(strict_types=1);

namespace Lescopr\CLI;

use Symfony\Component\Console\Application as BaseApplication;
use Lescopr\CLI\Commands\InitCommand;
use Lescopr\CLI\Commands\StartCommand;
use Lescopr\CLI\Commands\StopCommand;
use Lescopr\CLI\Commands\StatusCommand;
use Lescopr\CLI\Commands\DiagnoseCommand;
use Lescopr\CLI\Commands\ResetCommand;

/**
 * Lescopr CLI application entry point.
 */
class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Lescopr PHP SDK', '0.1.0');

        $this->addCommands([
            new InitCommand(),
            new StartCommand(),
            new StopCommand(),
            new StatusCommand(),
            new DiagnoseCommand(),
            new ResetCommand(),
        ]);

        $this->setDefaultCommand('list');
    }
}

