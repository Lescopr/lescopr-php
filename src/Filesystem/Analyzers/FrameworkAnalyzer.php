<?php

declare(strict_types=1);

namespace Lescopr\Filesystem\Analyzers;

/**
 * Detects PHP frameworks from project files.
 * Compatible with PHP 7.4+.
 */
class FrameworkAnalyzer
{
    /**
     * Framework detection patterns.
     *
     * Structure per framework:
     *   'files'            => required files (all must exist)
     *   'content_patterns' => [ 'filename_glob' => [ regex, ... ] ]
     *   'optional_files'   => extra files that bump confidence
     *   'confidence_weights' => [ 'files', 'content_patterns', 'optional_files' ]
     *
     * @var array<string, array<string, mixed>>
     */
    private const PATTERNS = [
        // ─────────────────── PHP ───────────────────
        // Note: composer.json serialized with json_encode escapes "/" as "\/"
        // We match both the escaped and unescaped form to work with:
        //   - Real composer.json files (unescaped slashes are valid JSON)
        //   - PHP json_encode() output which escapes slashes
        'laravel' => [
            'parent_stack_type' => 'php',
            'files'             => ['composer.json'],
            'content_patterns'  => [
                // Matches: "laravel/framework" or "laravel\/framework"
                'composer.json' => ['laravel/framework', 'laravel\/framework', 'laravel/laravel', 'laravel\/laravel'],
            ],
            'optional_files' => ['artisan', 'config/app.php', 'routes/web.php'],
            'confidence_weights' => ['files' => 30, 'content_patterns' => 50, 'optional_files' => 20],
        ],
        'symfony' => [
            'parent_stack_type' => 'php',
            'files'             => ['composer.json'],
            'content_patterns'  => [
                'composer.json' => ['symfony/symfony', 'symfony\/symfony', 'symfony/framework-bundle', 'symfony\/framework-bundle'],
            ],
            'optional_files' => ['config/bundles.php', 'src/Kernel.php', 'bin/console'],
            'confidence_weights' => ['files' => 30, 'content_patterns' => 60, 'optional_files' => 10],
        ],
        'lumen' => [
            'parent_stack_type' => 'php',
            'files'             => ['composer.json'],
            'content_patterns'  => [
                'composer.json' => ['laravel/lumen-framework', 'laravel\/lumen-framework'],
            ],
            'optional_files' => ['bootstrap/app.php'],
            'confidence_weights' => ['files' => 30, 'content_patterns' => 60, 'optional_files' => 10],
        ],
        'slim' => [
            'parent_stack_type' => 'php',
            'files'             => ['composer.json'],
            'content_patterns'  => [
                'composer.json' => ['slim/slim', 'slim\/slim'],
            ],
            'confidence_weights' => ['files' => 30, 'content_patterns' => 70],
        ],
        'codeigniter' => [
            'parent_stack_type' => 'php',
            'files'             => ['composer.json'],
            'content_patterns'  => [
                'composer.json' => ['codeigniter4/framework', 'codeigniter4\/framework', 'codeigniter/framework', 'codeigniter\/framework'],
            ],
            'confidence_weights' => ['files' => 30, 'content_patterns' => 70],
        ],
        'poo' => [
            'parent_stack_type' => 'php',
            'files'             => [],
            'content_patterns'  => [],
            'optional_files'    => ['composer.json', 'index.php', 'public/index.php'],
            'confidence_weights' => ['optional_files' => 100],
        ],
    ];

    /**
     * @param array<string, string> $fileContents  filepath => content
     * @return array<int, array<string, mixed>>     detected frameworks with confidence
     */
    public function detect(array $fileContents): array
    {
        $detected = [];

        foreach (self::PATTERNS as $name => $pattern) {
            $confidence = $this->calculateConfidence($name, $pattern, $fileContents);

            if ($confidence >= 50) {
                $detected[] = [
                    'name'              => $name,
                    'parent_stack_type' => $pattern['parent_stack_type'],
                    'confidence'        => $confidence,
                ];
            }
        }

        // Sort by confidence descending
        usort($detected, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        if (empty($detected)) {
            return $this->fallbackDetection($fileContents);
        }

        return $detected;
    }

    // ─────────────────── private ───────────────────

    /**
     * @param array<string, mixed>  $patternData
     * @param array<string, string> $fileContents
     */
    private function calculateConfidence(string $name, array $patternData, array $fileContents): int
    {
        $confidence = 0;
        $weights    = $patternData['confidence_weights'] ?? [
            'files' => 50, 'content_patterns' => 40, 'optional_files' => 10,
        ];

        // Required files
        if (!empty($patternData['files']) && $this->checkRequiredFiles($patternData['files'], $fileContents)) {
            $confidence += (int) ($weights['files'] ?? 50);
        }

        // Content patterns
        if (!empty($patternData['content_patterns']) && $this->checkContentPatterns($patternData['content_patterns'], $fileContents)) {
            $confidence += (int) ($weights['content_patterns'] ?? 40);
        }

        // Optional files
        if (!empty($patternData['optional_files']) && $this->checkOptionalFiles($patternData['optional_files'], $fileContents)) {
            $confidence += (int) ($weights['optional_files'] ?? 10);
        }

        return min($confidence, 100);
    }

    /**
     * @param string[]              $requiredFiles
     * @param array<string, string> $fileContents
     */
    private function checkRequiredFiles(array $requiredFiles, array $fileContents): bool
    {
        foreach ($requiredFiles as $required) {
            $found = false;
            foreach (array_keys($fileContents) as $path) {
                if (
                    substr($path, -strlen('/' . $required)) === '/' . $required
                    || $path === $required
                    || basename($path) === $required
                ) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, string[]> $contentPatterns
     * @param array<string, string>   $fileContents
     */
    private function checkContentPatterns(array $contentPatterns, array $fileContents): bool
    {
        foreach ($contentPatterns as $fileGlob => $patterns) {
            $matchingFiles = $this->findMatchingFiles($fileGlob, $fileContents);
            foreach ($matchingFiles as $path) {
                $content = $fileContents[$path] ?? '';
                foreach ($patterns as $pattern) {
                    if (strpos($content, $pattern) !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param string[]              $optionalFiles
     * @param array<string, string> $fileContents
     */
    private function checkOptionalFiles(array $optionalFiles, array $fileContents): bool
    {
        foreach ($optionalFiles as $optional) {
            foreach (array_keys($fileContents) as $path) {
                if (
                    substr($path, -strlen('/' . $optional)) === '/' . $optional
                    || $path === $optional
                    || basename($path) === $optional
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<string, string> $fileContents
     * @return string[]
     */
    private function findMatchingFiles(string $fileGlob, array $fileContents): array
    {
        if (strpos($fileGlob, '*') === 0) {
            $suffix = substr($fileGlob, 1);
            return array_values(array_filter(array_keys($fileContents), function ($p) use ($suffix) {
                return substr($p, -strlen($suffix)) === $suffix;
            }));
        }
        return array_values(array_filter(array_keys($fileContents), function ($p) use ($fileGlob) {
            return substr($p, -strlen('/' . $fileGlob)) === '/' . $fileGlob
                || $p === $fileGlob
                || basename($p) === $fileGlob;
        }));
    }

    /**
     * @param array<string, string> $fileContents
     * @return array<int, array<string, mixed>>
     */
    private function fallbackDetection(array $fileContents): array
    {
        // If there's a composer.json but no specific framework detected → generic PHP/POO
        foreach (array_keys($fileContents) as $path) {
            if (basename($path) === 'composer.json') {
                return [[
                    'name'              => 'poo',
                    'parent_stack_type' => 'php',
                    'confidence'        => 40,
                    'note'              => 'composer.json found but no specific framework detected',
                ]];
            }
        }
        // Minimal PHP project
        foreach (array_keys($fileContents) as $path) {
            if (substr($path, -4) === '.php') {
                return [[
                    'name'              => 'poo',
                    'parent_stack_type' => 'php',
                    'confidence'        => 30,
                    'note'              => 'PHP files found, vanilla/POO project assumed',
                ]];
            }
        }
        return [];
    }
}

