<?php

namespace SonnaLabs\Lescopr\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use SonnaLabs\Lescopr\Config;
use SonnaLabs\Lescopr\ConfigException;

class ConfigTest extends TestCase
{
    private vfsStreamDirectory $fileSystem;
    private string $configPath;
    private string $gitignorePath;

    protected function setUp(): void
    {
        $this->fileSystem = vfsStream::setup('root');
        $this->configPath = vfsStream::url('root/.lescoprrc.json');
        $this->gitignorePath = vfsStream::url('root/.gitignore');
    }

    public function testReadConfigSuccess(): void
    {
        $configData = [
            'apiKey' => 'test-api-key',
            'instanceId' => '123e4567-e89b-12d3-a456-426614174000',
            'companyName' => 'Test Company',
            'createdAt' => '2025-04-20T10:00:00+00:00',
        ];
        
        file_put_contents($this->configPath, json_encode($configData));
        
        $config = new Config();
        $result = $config->readConfig($this->configPath);
        
        $this->assertEquals($configData, $result);
    }
    
    public function testReadConfigFileMissing(): void
    {
        $config = new Config();
        $result = $config->readConfig($this->configPath);
        
        $this->assertNull($result);
    }
    
    public function testReadConfigInvalidJson(): void
    {
        file_put_contents($this->configPath, '{invalid: json}');
        
        $config = new Config();
        
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Failed to parse configuration file');
        
        $config->readConfig($this->configPath);
    }
    
    public function testWriteConfigSuccess(): void
    {
        $configData = [
            'apiKey' => 'new-test-key',
            'instanceId' => '123e4567-e89b-12d3-a456-426614174000',
        ];
        
        $config = new Config();
        $config->writeConfig($this->configPath, $configData);
        
        $this->assertFileExists($this->configPath);
        $written = json_decode(file_get_contents($this->configPath), true);
        $this->assertEquals($configData, $written);
    }
    
    public function testDeleteConfig(): void
    {
        // Create a file to delete
        file_put_contents($this->configPath, '{"test": "data"}');
        $this->assertFileExists($this->configPath);
        
        $config = new Config();
        $config->deleteConfig($this->configPath);
        
        $this->assertFileDoesNotExist($this->configPath);
    }
    
    public function testDeleteConfigNonExistentFile(): void
    {
        $config = new Config();
        // This should not throw an exception
        $config->deleteConfig($this->configPath);
        
        $this->assertFileDoesNotExist($this->configPath);
    }
    
    public function testEnsureGitignoreCreateNew(): void
    {
        $config = new Config();
        $config->ensureGitignore(vfsStream::url('root'));
        
        $this->assertFileExists($this->gitignorePath);
        $content = file_get_contents($this->gitignorePath);
        $this->assertStringContainsString('.lescoprrc.json', $content);
    }
    
    public function testEnsureGitignoreAlreadyExists(): void
    {
        file_put_contents($this->gitignorePath, "node_modules/\nvendor/\n");
        
        $config = new Config();
        $config->ensureGitignore(vfsStream::url('root'));
        
        $content = file_get_contents($this->gitignorePath);
        $this->assertStringContainsString("node_modules/", $content);
        $this->assertStringContainsString("vendor/", $content);
        $this->assertStringContainsString(".lescoprrc.json", $content);
    }
    
    public function testEnsureGitignoreAlreadyIncluded(): void
    {
        file_put_contents($this->gitignorePath, "node_modules/\nvendor/\n.lescoprrc.json\n");
        $originalContent = file_get_contents($this->gitignorePath);
        
        $config = new Config();
        $config->ensureGitignore(vfsStream::url('root'));
        
        $content = file_get_contents($this->gitignorePath);
        $this->assertEquals($originalContent, $content);
    }
}