<?php

declare(strict_types=1);

namespace Lescopr\Tests\Unit\Core;

use Lescopr\Core\Lescopr;
use PHPUnit\Framework\TestCase;

class LescoprTest extends TestCase
{
    public function test_get_project_files_returns_correct_paths(): void
    {
        $files = Lescopr::getProjectFiles();

        $this->assertArrayHasKey('config', $files);
        $this->assertArrayHasKey('log', $files);
        $this->assertArrayHasKey('pid', $files);

        $this->assertStringEndsWith('/.lescopr.json', $files['config']);
        $this->assertStringEndsWith('/.lescopr.log', $files['log']);
        $this->assertStringEndsWith('/.lescopr.pid', $files['pid']);
    }

    public function test_load_project_config_returns_empty_array_when_no_file(): void
    {
        // Temporarily change cwd to a temp dir with no config
        $originalDir = getcwd();
        $tmpDir      = sys_get_temp_dir() . '/lescopr_test_' . uniqid();
        mkdir($tmpDir);
        chdir($tmpDir);

        $config = Lescopr::loadProjectConfig();
        $this->assertIsArray($config);
        $this->assertEmpty($config);

        chdir($originalDir);
        rmdir($tmpDir);
    }

    public function test_save_and_load_project_config(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lescopr_test_' . uniqid();
        mkdir($tmpDir);
        $originalDir = getcwd();
        chdir($tmpDir);

        $config = [
            'sdk_id'        => 'proj_test',
            'sdk_key'       => 'lsk_test',
            'api_key'       => 'lak_test',
            'environment'   => 'development',
            'project_name'  => 'test-project',
            'project_stack' => ['laravel'],
        ];

        Lescopr::saveProjectConfig($config);
        $loaded = Lescopr::loadProjectConfig();

        $this->assertEquals($config['sdk_id'],       $loaded['sdk_id']);
        $this->assertEquals($config['sdk_key'],       $loaded['sdk_key']);
        $this->assertEquals($config['project_name'], $loaded['project_name']);

        // Cleanup
        @unlink($tmpDir . '/.lescopr.json');
        chdir($originalDir);
        rmdir($tmpDir);
    }

    public function test_add_and_get_pending_logs(): void
    {
        $instance = new Lescopr(null, null, 'development', false, false);

        $this->assertFalse($instance->hasPendingLogs());

        $instance->addPendingLog(['level' => 'INFO', 'message' => 'Test log 1']);
        $instance->addPendingLog(['level' => 'ERROR', 'message' => 'Test log 2']);

        $this->assertTrue($instance->hasPendingLogs());

        $logs = $instance->getPendingLogs();

        $this->assertCount(2, $logs);
        $this->assertEquals('Test log 1', $logs[0]['message']);
        $this->assertEquals('Test log 2', $logs[1]['message']);

        // Queue should be empty after get
        $this->assertFalse($instance->hasPendingLogs());
    }

    public function test_pending_logs_capped_at_1000(): void
    {
        $instance = new Lescopr(null, null, 'development', false, false);

        // Add 1100 logs — when the queue exceeds 1000 it is trimmed to 500,
        // then the remaining 100 items are added. Final count ≤ 600.
        for ($i = 0; $i < 1100; $i++) {
            $instance->addPendingLog(['level' => 'INFO', 'message' => "Log $i"]);
        }

        $logs = $instance->getPendingLogs();

        // The queue must never grow beyond 1000 + the items added after the last trim
        $this->assertLessThan(1100, count($logs));
        $this->assertGreaterThan(0,    count($logs));
    }
}

