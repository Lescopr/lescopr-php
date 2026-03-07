<?php

declare(strict_types=1);

namespace Lescopr\Filesystem\Core;

/**
 * Scans the project directory structure and reads key file contents.
 * Compatible with PHP 7.4+.
 */
class ProjectScanner
{
    /** Directories to skip entirely */
    private const IGNORED_DIRS = [
        '.git', '.svn', '.hg', '.vscode', '.idea',
        'node_modules', 'vendor', '__pycache__', '.pytest_cache',
        'venv', '.venv', 'env', 'build', 'dist', 'target',
        '.next', '.nuxt', '.cache', 'storage/framework',
        'bootstrap/cache', 'coverage', 'tmp', 'temp',
    ];

    /** File prefixes to ignore */
    private const IGNORED_PREFIXES = ['.', '~', '#'];

    /** Key files whose content is worth reading for framework detection */
    private const FILES_TO_ANALYZE = [
        'composer.json', 'composer.lock',
        'package.json',
        'artisan', 'index.php',
        'config/app.php', 'config/bundles.php',
        'routes/web.php', 'routes/api.php',
        'src/Kernel.php', 'bootstrap/app.php',
        '.env', '.env.example',
        'README.md', 'readme.md',
        'Dockerfile', 'docker-compose.yml',
    ];

    /** Max file size to read (1 MB) */
    private const MAX_FILE_SIZE = 1048576;

    /** @var string */
    private $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/');
    }

    /**
     * Returns [projectTree, fileContents]
     *
     * @return array{0: array<string, list<string>>, 1: array<string, string>}
     */
    public function scanStructure(): array
    {
        $projectTree  = [];
        $fileContents = [];
        $rootPath     = $this->rootPath;
        $ignoredDirs  = self::IGNORED_DIRS;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(
                    $this->rootPath,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                function (\SplFileInfo $file, $key, \RecursiveDirectoryIterator $iterator) use ($ignoredDirs) {
                    if ($iterator->hasChildren() && in_array($file->getFilename(), $ignoredDirs, true)) {
                        return false;
                    }
                    if (!$file->isDir()) {
                        foreach (self::IGNORED_PREFIXES as $prefix) {
                            if (strpos($file->getFilename(), $prefix) === 0) {
                                return false;
                            }
                        }
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($this->rootPath) + 1);

            if ($item->isDir()) {
                if (!isset($projectTree[$relative])) {
                    $projectTree[$relative] = [];
                }
            } elseif ($item->isFile()) {
                $dir = dirname($relative);
                if (!isset($projectTree[$dir])) {
                    $projectTree[$dir] = [];
                }
                $projectTree[$dir][] = $item->getFilename();

                if ($this->shouldAnalyzeFile($item->getFilename(), $relative, $item->getSize())) {
                    $content = @file_get_contents($item->getPathname());
                    if ($content !== false && $content !== '') {
                        $fileContents[$relative] = $content;
                    }
                }
            }
        }

        return [$projectTree, $fileContents];
    }

    private function shouldAnalyzeFile(string $filename, string $relativePath, int $size): bool
    {
        if ($size > self::MAX_FILE_SIZE) {
            return false;
        }

        foreach (self::FILES_TO_ANALYZE as $pattern) {
            if (
                $filename === $pattern
                || $relativePath === $pattern
                || substr($relativePath, -strlen('/' . $pattern)) === '/' . $pattern
            ) {
                return true;
            }
        }

        return false;
    }
}

