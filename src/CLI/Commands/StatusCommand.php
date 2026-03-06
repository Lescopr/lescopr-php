<?php

declare(strict_types=1);

namespace Lescopr\CLI\Commands;

use Lescopr\Core\Lescopr;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'status', description: 'Show the current status of the Lescopr daemon and project')]
class StatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $status = Lescopr::getStatus();

        $io->title('Lescopr Status');

        $io->definitionList(
            ['Project path'  => $status['project_path']],
            ['Config file'   => $status['config_file']],
            ['Config exists' => $status['config_exists'] ? '✅ Yes' : '❌ No'],
        );

        if (!$status['config_exists']) {
            $io->warning("Project not initialised. Run 'lescopr init' first.");
            return Command::SUCCESS;
        }

        $config = $status['config'];
        $io->section('Project');
        $io->definitionList(
            ['SDK ID'      => $config['sdk_id']      ?? 'N/A'],
            ['Name'        => $config['project_name'] ?? 'N/A'],
            ['Stack'       => implode(', ', $config['project_stack'] ?? [])],
            ['Environment' => strtoupper($config['environment'] ?? 'N/A')],
        );

        $io->section('Daemon');
        if ($status['daemon_running']) {
            $io->success('🟢 Running (PID: ' . $status['daemon_pid'] . ')');
        } else {
            $io->caution('🔴 Stopped — run "lescopr start" to activate monitoring.');
        }

        $io->text('Log file: ' . $status['log_file']);

        return Command::SUCCESS;
    }
}

