<?php

declare(strict_types=1);

namespace Lescopr\CLI\Commands;

use Lescopr\Core\Lescopr;
use Lescopr\Network\HttpClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'diagnose', description: 'Run a full connectivity and configuration diagnostic')]
class DiagnoseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('details',      'd', InputOption::VALUE_NONE, 'Show detailed output including last log lines')
            ->addOption('check-server', null, InputOption::VALUE_NONE, 'Test connectivity to api.lescopr.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $verbose = $input->getOption('details');

        $io->title('Lescopr Diagnose');

        // ── PHP environment ──────────────────────────────────────────────────
        $io->section('PHP Environment');
        $io->definitionList(
            ['PHP version'  => PHP_VERSION],
            ['OS'           => PHP_OS],
            ['SAPI'         => PHP_SAPI],
            ['ext-pcntl'    => extension_loaded('pcntl')   ? '✅' : '❌ (daemon unavailable)'],
            ['ext-posix'    => extension_loaded('posix')   ? '✅' : '❌'],
            ['ext-openssl'  => extension_loaded('openssl') ? '✅' : '❌'],
        );

        // ── Config ───────────────────────────────────────────────────────────
        $io->section('Configuration');
        $status = Lescopr::getStatus();
        $io->definitionList(
            ['Config file'   => $status['config_file']],
            ['Config exists' => $status['config_exists'] ? '✅ Yes' : '❌ Not found'],
        );

        if ($status['config_exists']) {
            $cfg = $status['config'];
            $io->definitionList(
                ['sdk_id'      => $cfg['sdk_id']       ?? '❌ Missing'],
                ['sdk_key'     => isset($cfg['sdk_key'])  ? '***' . substr($cfg['sdk_key'], -4)  : '❌ Missing'],
                ['api_key'     => isset($cfg['api_key'])  ? '***' . substr($cfg['api_key'], -4)  : '❌ Missing'],
                ['environment' => $cfg['environment']  ?? '❌ Missing'],
                ['project'     => $cfg['project_name'] ?? '❌ Missing'],
                ['stack'       => implode(', ', $cfg['project_stack'] ?? [])],
            );
        }

        // ── Daemon ───────────────────────────────────────────────────────────
        $io->section('Daemon');
        if ($status['daemon_running']) {
            $io->success('🟢 Running (PID: ' . $status['daemon_pid'] . ')');
        } else {
            $io->caution('🔴 Stopped');
        }
        $io->text('Log file: ' . $status['log_file']);

        if ($verbose && file_exists($status['log_file'])) {
            $io->section('Last 20 log lines');
            $lines = array_slice(file($status['log_file']) ?: [], -20);
            $io->text($lines);
        }

        // ── Server connectivity ───────────────────────────────────────────────
        if ($input->getOption('check-server')) {
            $io->section('Server Connectivity');
            $io->text('Testing https://api.lescopr.com ...');

            try {
                $http     = new HttpClient(5);
                $response = $http->get(Lescopr::BASE_URL . '/health/');

                if ($response['ok']) {
                    $io->success('✅ api.lescopr.com is reachable (HTTP ' . $response['status'] . ')');
                } else {
                    $io->error('❌ api.lescopr.com returned HTTP ' . $response['status']);
                }
            } catch (\Throwable $e) {
                $io->error('❌ Cannot reach api.lescopr.com: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}

