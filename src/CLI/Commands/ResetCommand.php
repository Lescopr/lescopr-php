<?php

declare(strict_types=1);

namespace Lescopr\CLI\Commands;

use Lescopr\Core\Lescopr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'reset', description: 'Remove Lescopr configuration and stop the daemon')]
class ResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('force',       'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('keep-config', null, InputOption::VALUE_NONE, 'Stop daemon but keep .lescopr.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = new SymfonyStyle($input, $output);
        $force      = $input->getOption('force');
        $keepConfig = $input->getOption('keep-config');

        $files = Lescopr::getProjectFiles();

        if (!file_exists($files['config']) && !file_exists($files['pid'])) {
            $io->info('No Lescopr installation found in this project.');
            return Command::SUCCESS;
        }

        if (!$force) {
            $action = $keepConfig ? 'stop the daemon' : 'remove ALL Lescopr configuration and stop the daemon';
            if (!$io->confirm("This will $action. Continue?", false)) {
                $io->text('Aborted.');
                return Command::SUCCESS;
            }
        }

        $result = Lescopr::resetProject($keepConfig);

        if ($result['daemon_stopped']) {
            $io->text('✅ Daemon stopped.');
        }
        if ($result['config_removed']) {
            $io->text('✅ Configuration removed.');
        }
        if ($result['logs_cleaned']) {
            $io->text('✅ Log files cleaned.');
        }

        $io->success('Reset complete.');

        return Command::SUCCESS;
    }
}

