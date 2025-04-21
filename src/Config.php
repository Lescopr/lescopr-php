<?php

declare(strict_types=1);

namespace SonnaLabs\Lescopr;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Config
{
    public const DEFAULT_FILENAME = '.lescoprrc.json';
    private const GITIGNORE_FILENAME = '.gitignore';

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Read configuration from a file.
     * @param string $configPath 
     * @return array<string, mixed>|null
     * @throws ConfigException
     */
    public function readConfig(string $configPath): ?array
    {
        if (!file_exists($configPath)) {
            $this->logger->debug("[Config] Config file not found at {$configPath}");
            return null;
        }

        try {
            $content = file_get_contents($configPath);
            if ($content === false) {
                throw new ConfigException("Failed to read config file: {$configPath}");
            }
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error("[Config] Error parsing config file {$configPath}", ['exception' => $e]);
            throw new ConfigException("Failed to parse configuration file: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            $this->logger->error("[Config] Error reading config file {$configPath}", ['exception' => $e]);
            throw new ConfigException("Failed to read configuration file: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Write configuration to a file.
     * @param string $configPath
     * @param array<string, mixed> $configData
     * @throws ConfigException
     */
    public function writeConfig(string $configPath, array $configData): void
    {
        try {
            $json = json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($configPath, $json) === false) {
                throw new ConfigException("Failed to write config file: {$configPath}");
            }
            $this->logger->info("[Config] Configuration written to {$configPath}");
        } catch (\JsonException $e) {
            $this->logger->error("[Config] Error encoding config data for {$configPath}", ['exception' => $e]);
            throw new ConfigException("Failed to encode configuration data: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            $this->logger->error("[Config] Error writing config file {$configPath}", ['exception' => $e]);
            throw new ConfigException("Failed to write configuration file: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Delete the configuration file.
     * @param string $configPath 
     * @throws ConfigException
     */
    public function deleteConfig(string $configPath): void
    {
        if (!file_exists($configPath)) {
            $this->logger->debug("[Config] Config file {$configPath} does not exist, nothing to delete.");
            return;
        }

        if (!unlink($configPath)) {
            $this->logger->error("[Config] Failed to delete config file {$configPath}");
            throw new ConfigException("Failed to delete configuration file: {$configPath}");
        }
        $this->logger->info("[Config] Configuration file deleted: {$configPath}");
    }

    /**
     * Ensure the configuration filename is in .gitignore.
     * @param string $projectDir 
     * @param string $configFilename 
     * @throws ConfigException
     */
    public function ensureGitignore(string $projectDir, string $configFilename = self::DEFAULT_FILENAME): void
    {
        $gitignorePath = $projectDir . DIRECTORY_SEPARATOR . self::GITIGNORE_FILENAME;
        $this->logger->debug("[Config] Checking gitignore at {$gitignorePath} for {$configFilename}");
        $gitignoreContent = '';

        try {
            if (file_exists($gitignorePath)) {
                $gitignoreContent = file_get_contents($gitignorePath);
                if ($gitignoreContent === false) {
                    throw new ConfigException("Failed to read .gitignore file: {$gitignorePath}");
                }
            }

            $lines = explode("\n", str_replace("\r\n", "\n", $gitignoreContent));
            $isAlreadyIgnored = false;
            foreach ($lines as $line) {
                if (trim($line) === $configFilename) {
                    $isAlreadyIgnored = true;
                    break;
                }
            }

            if (!$isAlreadyIgnored) {
                $this->logger->info("[Config] Adding '{$configFilename}' to {$gitignorePath}");
                $gitignoreContent .= (empty($gitignoreContent) ? '' : "\n") . $configFilename . "\n";
                if (file_put_contents($gitignorePath, $gitignoreContent) === false) {
                    throw new ConfigException("Failed to write to .gitignore file: {$gitignorePath}");
                }
            } else {
                $this->logger->debug("[Config] '{$configFilename}' is already in {$gitignorePath}");
            }
        } catch (\Throwable $e) {
            $this->logger->error("[Config] Error processing .gitignore file {$gitignorePath}", ['exception' => $e]);
            throw new ConfigException("Failed to ensure config is ignored: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}

/**
 * Custom exception for Lescopr configuration related errors.
 */
class ConfigException extends \RuntimeException {}