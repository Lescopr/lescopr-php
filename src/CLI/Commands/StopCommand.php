<?php

declare(strict_types=1);

namespace Lescopr\CLI\Commands;

use Lescopr\Core\Lescopr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'stop', description: 'Stop the Lescopr monitoring daemon')]
class StopCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $result = Lescopr::stopDaemon();

        if ($result['success']) {
            $io->success('Daemon stopped successfully.');
        } else {
            $io->warning($result['error']);
        }

        return Command::SUCCESS;
    }
}

