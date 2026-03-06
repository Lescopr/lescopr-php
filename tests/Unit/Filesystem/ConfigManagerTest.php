<?php

declare(strict_types=1);

namespace Lescopr\Tests\Unit\Filesystem;

use Lescopr\Filesystem\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    private string $tmpDir;
    private ConfigManager $manager;

    protected function setUp(): void
    {
        $this->tmpDir  = sys_get_temp_dir() . '/lescopr_config_test_' . uniqid();
        mkdir($this->tmpDir);
        $this->manager = new ConfigManager($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $file = $this->tmpDir . '/' . ConfigManager::CONFIG_FILENAME;
        if (file_exists($file)) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function test_exists_returns_false_when_no_file(): void
    {
        $this->assertFalse($this->manager->exists());
    }

    public function test_load_returns_empty_array_when_no_file(): void
    {
        $this->assertSame([], $this->manager->load());
    }

    public function test_save_and_load(): void
    {
        $config = [
            'sdk_id'      => 'proj_123',
            'sdk_key'     => 'lsk_abc',
            'environment' => 'development',
        ];

        $this->assertTrue($this->manager->save($config, false));
        $this->assertTrue($this->manager->exists());

        $loaded = $this->manager->load();

        $this->assertEquals('proj_123', $loaded['sdk_id']);
        $this->assertEquals('lsk_abc',  $loaded['sdk_key']);
    }

    public function test_save_merges_by_default(): void
    {
        $this->manager->save(['sdk_id' => 'proj_123'], false);
        $this->manager->save(['sdk_key' => 'lsk_abc']);

        $loaded = $this->manager->load();

        $this->assertEquals('proj_123', $loaded['sdk_id']);
        $this->assertEquals('lsk_abc',  $loaded['sdk_key']);
    }

    public function test_delete_removes_file(): void
    {
        $this->manager->save(['sdk_id' => 'proj_123'], false);
        $this->assertTrue($this->manager->exists());

        $this->manager->delete();
        $this->assertFalse($this->manager->exists());
    }
}

