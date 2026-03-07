<?php

declare(strict_types=1);

namespace Lescopr\Filesystem\Analyzers;

use Lescopr\Filesystem\Core\ProjectScanner;

/**
 * Main project analyzer — mirrors Python's ProjectAnalyzer.
 * Scans the project, detects frameworks and returns a payload
 * ready to send to POST /api/v1/sdk/verify/.
 */
class ProjectAnalyzer
{
    private string $projectRoot;
    private ProjectScanner $scanner;
    private FrameworkAnalyzer $frameworkAnalyzer;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot       = realpath($projectRoot ?? getcwd()) ?: getcwd();
        $this->scanner           = new ProjectScanner($this->projectRoot);
        $this->frameworkAnalyzer = new FrameworkAnalyzer();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function analyze(): ?array
    {
        try {
            [$projectTree, $fileContents] = $this->scanner->scanStructure();

            $projectName        = $this->extractProjectName($fileContents);
            $detectedFrameworks = $this->frameworkAnalyzer->detect($fileContents);
            $technologies       = $this->detectTechnologies($fileContents);

            return [
                'project_name'               => $projectName,
                'project_root'               => $this->projectRoot,
                'project_structure_summary'  => $this->createStructureSummary($projectTree, $fileContents),
                'technologies'               => $technologies,
                'detected_frameworks'        => $detectedFrameworks,
                'file_contents_summary'      => $this->createContentSummary($fileContents),
                'sdk_client_version'         => '0.1.0',
                'sdk_language'               => 'php',
                'php_version'                => PHP_VERSION,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─────────────────── private ───────────────────

    /**
     * @param array<string, string> $fileContents
     */
    private function extractProjectName(array $fileContents): string
    {
        // composer.json
        foreach ($fileContents as $path => $content) {
            if (basename($path) === 'composer.json') {
                try {
                    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($data['name']) && is_string($data['name'])) {
                        // Strip vendor prefix: "vendor/package" → "package"
                        $parts = explode('/', $data['name']);
                        return end($parts);
                    }
                } catch (\Throwable $e) {}
            }
        }

        // package.json
        foreach ($fileContents as $path => $content) {
            if (basename($path) === 'package.json') {
                try {
                    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($data['name']) && is_string($data['name'])) {
                        return $data['name'];
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Fallback: folder name
        return basename($this->projectRoot);
    }

    /**
     * @param array<string, list<string>> $projectTree
     * @param array<string, string>       $fileContents
     * @return array<string, mixed>
     */
    private function createStructureSummary(array $projectTree, array $fileContents): array
    {
        $totalFiles = array_sum(array_map('count', $projectTree));
        $totalDirs  = count($projectTree);

        return [
            'root_dir_name'       => basename($this->projectRoot),
            'total_files_scanned' => $totalFiles,
            'total_directories'   => $totalDirs,
            'key_files_found'     => array_map('basename', array_keys($fileContents)),
            'project_size'        => $this->categorizeSize($totalFiles),
        ];
    }

    /**
     * @param array<string, string> $fileContents
     * @return array<string, int>  extension => percentage
     */
    private function detectTechnologies(array $fileContents): array
    {
        $extensionMap = [
            'php' => 'PHP', 'js' => 'JavaScript', 'ts' => 'TypeScript',
            'py'  => 'Python', 'css' => 'CSS', 'html' => 'HTML',
            'json' => 'JSON', 'yaml' => 'YAML', 'yml' => 'YAML',
            'md'  => 'Markdown', 'sh' => 'Shell', 'sql' => 'SQL',
        ];

        $counts = [];
        foreach (array_keys($fileContents) as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $tech = $extensionMap[$ext] ?? null;
            if ($tech) {
                $counts[$tech] = ($counts[$tech] ?? 0) + 1;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }

        $percentages = [];
        foreach ($counts as $tech => $count) {
            $percentages[$tech] = (int) round($count / $total * 100);
        }
        arsort($percentages);
        return $percentages;
    }

    /**
     * @param array<string, string> $fileContents
     * @return array<string, string>
     */
    private function createContentSummary(array $fileContents): array
    {
        $summary = [];
        foreach ($fileContents as $path => $content) {
            $summary[$path] = mb_strlen($content) > 200
                ? mb_substr($content, 0, 200) . '...'
                : $content;
        }
        return $summary;
    }

    private function categorizeSize(int $totalFiles): string
    {
        if ($totalFiles < 10) {
            return 'small';
        }
        if ($totalFiles < 50) {
            return 'medium';
        }
        if ($totalFiles < 200) {
            return 'large';
        }
        return 'very_large';
    }
}

