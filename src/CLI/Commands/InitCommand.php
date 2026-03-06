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

#[AsCommand(name: 'init', description: 'Initialise the Lescopr SDK in the current project')]
class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('sdk-key',     null, InputOption::VALUE_REQUIRED, 'Your Lescopr SDK key')
            ->addOption('api-key',     null, InputOption::VALUE_OPTIONAL, 'Your Lescopr API key')
            ->addOption('environment', null, InputOption::VALUE_OPTIONAL, 'Environment (development|production)', 'development')
            ->addOption('no-start-daemon', null, InputOption::VALUE_NONE, 'Skip automatic daemon start after init');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Lescopr PHP SDK — init');

        $sdkKey      = $input->getOption('sdk-key');
        $apiKey      = $input->getOption('api-key') ?? 'lsk_api_key_12345';
        $environment = $input->getOption('environment');
        $noStartDaemon = $input->getOption('no-start-daemon');

        if (!$sdkKey) {
            $io->error('--sdk-key is required. Run: lescopr init --sdk-key YOUR_SDK_KEY');
            return Command::FAILURE;
        }

        $files = Lescopr::getProjectFiles();

        if (file_exists($files['config'])) {
            $io->warning('SDK already initialised in this project.');
            if (!$io->confirm('Do you want to re-initialise?', false)) {
                return Command::SUCCESS;
            }
        }

        $io->text('⏳ Initialising Lescopr SDK...');

        $result = Lescopr::initializeProject($apiKey, $sdkKey, $environment);

        if (!$result['success']) {
            $io->error('Initialisation failed: ' . $result['error']);
            return Command::FAILURE;
        }

        $config = $result['config'];
        $io->success('SDK initialised successfully!');
        $io->definitionList(
            ['SDK ID'        => $config['sdk_id']],
            ['Project'       => $config['project_name']],
            ['Stack'         => implode(', ', $config['project_stack'] ?? [])],
            ['Environment'   => strtoupper($environment)],
            ['Config file'   => $files['config']],
        );

        if (!$noStartDaemon) {
            $io->text('⏳ Starting daemon...');
            $daemonResult = Lescopr::startDaemon();
            if ($daemonResult['success']) {
                $io->success('Daemon started (PID: ' . $daemonResult['pid'] . ')');
            } else {
                $io->warning('Daemon could not be started: ' . $daemonResult['error']);
                $io->text('You can start it later with: lescopr start');
            }
        } else {
            $io->note('Run "lescopr start" to start the monitoring daemon.');
        }

        return Command::SUCCESS;
    }
}

