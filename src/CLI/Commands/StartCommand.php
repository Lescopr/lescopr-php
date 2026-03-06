<?php

declare(strict_types=1);

namespace Lescopr\CLI\Commands;

use Lescopr\Core\Lescopr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'start', description: 'Start the Lescopr monitoring daemon')]
class StartCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $files = Lescopr::getProjectFiles();

        if (!file_exists($files['config'])) {
            $io->error("Project not initialised. Run 'lescopr init' first.");
            return Command::FAILURE;
        }

        $io->text('⏳ Starting Lescopr daemon...');
        $result = Lescopr::startDaemon();

        if ($result['success']) {
            $io->success('Daemon started (PID: ' . $result['pid'] . ')');
            $io->text('Logs: ' . $result['log_file']);
        } else {
            $io->error($result['error']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

