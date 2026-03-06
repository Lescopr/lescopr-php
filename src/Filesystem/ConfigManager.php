<?php

declare(strict_types=1);

namespace Lescopr\Filesystem;

/**
 * Reads/writes .lescopr.json in the project root.
 */
class ConfigManager
{
    public const CONFIG_FILENAME = '.lescopr.json';

    private string $configFile;

    public function __construct(?string $configDir = null)
    {
        $this->configFile = ($configDir ?? getcwd()) . '/' . self::CONFIG_FILENAME;
    }

    public function exists(): bool
    {
        return file_exists($this->configFile) && is_file($this->configFile);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!$this->exists()) {
            return [];
        }

        try {
            $content = file_get_contents($this->configFile);
            if ($content === false) {
                return [];
            }
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function save(array $config, bool $merge = true): bool
    {
        try {
            $final = $merge ? array_merge($this->load(), $config) : $config;
            $json  = json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            file_put_contents($this->configFile, $json, LOCK_EX);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(): bool
    {
        if (!$this->exists()) {
            return true;
        }
        return @unlink($this->configFile);
    }

    public function getFilePath(): string
    {
        return $this->configFile;
    }
}

