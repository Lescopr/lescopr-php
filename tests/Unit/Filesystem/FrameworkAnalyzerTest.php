<?php

declare(strict_types=1);

namespace Lescopr\Tests\Unit\Filesystem;

use Lescopr\Filesystem\Analyzers\FrameworkAnalyzer;
use PHPUnit\Framework\TestCase;

class FrameworkAnalyzerTest extends TestCase
{
    private FrameworkAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new FrameworkAnalyzer();
    }

    public function test_detects_laravel(): void
    {
        $fileContents = [
            'composer.json' => json_encode([
                'require' => ['laravel/framework' => '^10.0'],
            ]),
            'artisan'        => '#!/usr/bin/env php',
            'routes/web.php' => '<?php Route::get("/", fn() => view("welcome"));',
        ];

        $result = $this->analyzer->detect($fileContents);

        $names = array_column($result, 'name');
        $this->assertContains('laravel', $names);

        $laravel = array_values(array_filter($result, fn($r) => $r['name'] === 'laravel'))[0];
        $this->assertGreaterThanOrEqual(50, $laravel['confidence']);
    }

    public function test_detects_symfony(): void
    {
        $fileContents = [
            'composer.json' => json_encode([
                'require' => ['symfony/framework-bundle' => '^6.0'],
            ]),
            'config/bundles.php' => '<?php return [];',
            'src/Kernel.php'     => '<?php class Kernel extends MicroKernelTrait {}',
        ];

        $result = $this->analyzer->detect($fileContents);

        $names = array_column($result, 'name');
        $this->assertContains('symfony', $names);
    }

    public function test_detects_slim(): void
    {
        $fileContents = [
            'composer.json' => json_encode([
                'require' => ['slim/slim' => '^4.0'],
            ]),
        ];

        $result = $this->analyzer->detect($fileContents);

        $names = array_column($result, 'name');
        $this->assertContains('slim', $names);
    }

    public function test_fallback_to_poo_with_composer(): void
    {
        $fileContents = [
            'composer.json' => json_encode(['require' => ['vendor/package' => '1.0']]),
        ];

        $result = $this->analyzer->detect($fileContents);

        // Should fall back to 'poo' detection
        $this->assertNotEmpty($result);
        $names = array_column($result, 'name');
        $this->assertContains('poo', $names);
    }

    public function test_returns_empty_for_unknown_project(): void
    {
        $result = $this->analyzer->detect([]);
        $this->assertIsArray($result);
    }

    public function test_confidence_does_not_exceed_100(): void
    {
        $fileContents = [
            'composer.json' => json_encode([
                'require' => ['laravel/framework' => '^10.0', 'laravel/laravel' => '^10.0'],
            ]),
            'artisan'              => '#!/usr/bin/env php',
            'config/app.php'       => '<?php return [];',
            'routes/web.php'       => '<?php',
        ];

        $result = $this->analyzer->detect($fileContents);

        foreach ($result as $framework) {
            $this->assertLessThanOrEqual(100, $framework['confidence']);
        }
    }
}

