<?php

declare(strict_types=1);

namespace Lescopr\Tests\Unit\Filesystem;

use Lescopr\Filesystem\Analyzers\ProjectAnalyzer;
use PHPUnit\Framework\TestCase;

class ProjectAnalyzerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/lescopr_pa_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function test_analyze_detects_laravel_project(): void
    {
        // Create minimal Laravel-like project structure
        $composerData = [
            'name'    => 'mycompany/my-laravel-app',
            'require' => ['laravel/framework' => '^10.0'],
        ];
        file_put_contents($this->tmpDir . '/composer.json', json_encode($composerData));
        mkdir($this->tmpDir . '/routes');
        file_put_contents($this->tmpDir . '/routes/web.php', '<?php');
        file_put_contents($this->tmpDir . '/artisan', '#!/usr/bin/env php');

        $analyzer = new ProjectAnalyzer($this->tmpDir);
        $result   = $analyzer->analyze();

        $this->assertNotNull($result);
        $this->assertEquals('my-laravel-app', $result['project_name']);
        $this->assertEquals('php', $result['sdk_language']);

        $frameworkNames = array_column($result['detected_frameworks'], 'name');
        $this->assertContains('laravel', $frameworkNames);
    }

    public function test_analyze_extracts_project_name_from_composer(): void
    {
        file_put_contents(
            $this->tmpDir . '/composer.json',
            json_encode(['name' => 'acme/my-api'])
        );

        $analyzer = new ProjectAnalyzer($this->tmpDir);
        $result   = $analyzer->analyze();

        $this->assertNotNull($result);
        $this->assertEquals('my-api', $result['project_name']);
    }

    public function test_analyze_falls_back_to_directory_name(): void
    {
        // No composer.json — project name should be directory name
        file_put_contents($this->tmpDir . '/index.php', '<?php echo "hello";');

        $analyzer = new ProjectAnalyzer($this->tmpDir);
        $result   = $analyzer->analyze();

        $this->assertNotNull($result);
        $this->assertEquals(basename($this->tmpDir), $result['project_name']);
    }

    public function test_analyze_returns_structure_summary(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode(['name' => 'test/app']));
        file_put_contents($this->tmpDir . '/index.php', '<?php');

        $analyzer = new ProjectAnalyzer($this->tmpDir);
        $result   = $analyzer->analyze();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('project_structure_summary', $result);
        $this->assertArrayHasKey('total_files_scanned', $result['project_structure_summary']);
    }

    // ─────────────────── helper ───────────────────

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}

