<?php

namespace SonnaLabs\Lescopr\Tests;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SonnaLabs\Lescopr\Api;
use SonnaLabs\Lescopr\Config;
use SonnaLabs\Lescopr\Lescopr;
use SonnaLabs\Lescopr\WebSocketClient;
use PHPUnit\Framework\MockObject\MockObject;
use SonnaLabs\Lescopr\LescoprException;

class LescoprTest extends TestCase
{
    private string $configPath;
    private $rootFs;

    protected function setUp(): void
    {
        $this->rootFs = vfsStream::setup('root');
        $this->configPath = vfsStream::url('root/.lescoprrc.json');
        Lescopr::resetSdkStateForTesting();
    }

    protected function tearDown(): void
    {
        Lescopr::resetSdkStateForTesting();
        $this->rootFs = null;
    }

    private function createMockApiClient(): MockObject
    {
        $mock = $this->createMock(Api::class);
        return $mock;
    }

    private function createMockWebSocketClient(): MockObject
    {
        $mock = $this->createMock(WebSocketClient::class);
        return $mock;
    }

    private function createMockConfig(array $configData = null): MockObject
    {
        $mock = $this->createMock(Config::class);

        if ($configData !== null) {
            $mock->method('readConfig')
                 ->willReturn($configData);
        } else {
            $mock->method('readConfig')
                 ->willReturn([]);
        }

        return $mock;
    }

    public function testInitWithValidOptions(): void
    {
        $mockApi = $this->createMockApiClient();
        $mockApi->expects($this->any())
            ->method('validateApiKey')
            ->willReturn([
                'isValid' => true,
                'companyName' => 'Test Company',
                'planStatus' => 'active',
                'planExpiry' => '2025-12-31'
            ]);

        $mockWs = $this->createMockWebSocketClient();
        $mockWs->expects($this->any())
            ->method('connect')
            ->willReturn(true);

        $result = Lescopr::init([
            'apiKey' => 'test-api-key',
            'instanceId' => 'test-instance-id'
        ]);

        $this->markTestIncomplete(
            'Cannot fully unit-test init() success due to internal dependency creation. Requires refactoring Lescopr::init() for dependency injection or integration testing.'
        );
    }

    public function testInitWithInvalidApiKey(): void
    {
        $mockApi = $this->createMockApiClient();
        $mockApi->expects($this->never())
             ->method('validateApiKey');

        $result = Lescopr::init([
            'apiKey' => 'invalid-key',
            'instanceId' => 'test-instance-id'
        ]);

        $this->assertFalse($result, "Initialization should return false with an invalid API key.");
        $this->assertFalse(Lescopr::isInitialized(), "SDK should not be initialized after failed init.");
    }

    public function testInitFromConfigFile(): void
    {
        $configData = [
            'apiKey' => 'file-api-key',
            'instanceId' => 'file-instance-id',
            'companyName' => 'File Company',
        ];
        file_put_contents($this->configPath, json_encode($configData));

        $mockConfig = $this->createMockConfig($configData);
        $mockApi = $this->createMockApiClient();
        $mockApi->expects($this->any())
            ->method('validateApiKey')
            ->willReturn([
                'isValid' => true,
                'companyName' => 'API Company Name',
                'planStatus' => 'trial',
                'planExpiry' => '2025-06-30'
            ]);
        $mockWs = $this->createMockWebSocketClient();
        $mockWs->expects($this->any())
               ->method('connect')
               ->willReturn(true);

        $reflection = new \ReflectionClass(Lescopr::class);
        $configProp = $reflection->getProperty('configHandler');
        $configProp->setAccessible(true);

        $result = Lescopr::init(['configPath' => $this->configPath]);

        $this->markTestIncomplete(
            'Cannot fully unit-test init() success from config due to internal dependency creation. Requires refactoring Lescopr::init() for dependency injection or integration testing.'
        );
    }

    public function testSendLog(): void
    {
        $reflection = new \ReflectionClass(Lescopr::class);

        $initializedProp = $reflection->getProperty('initialized');
        $initializedProp->setAccessible(true);
        $initializedProp->setValue(null, true);

        $instanceIdProp = $reflection->getProperty('instanceId');
        $instanceIdProp->setAccessible(true);
        $instanceIdProp->setValue(null, 'test-instance-log');

        $allowedProp = $reflection->getProperty('isAllowedToSend');
        $allowedProp->setAccessible(true);
        $allowedProp->setValue(null, true);

        $mockWs = $this->createMockWebSocketClient();
        $mockWs->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $mockWs->expects($this->once())
            ->method('send')
            ->with($this->callback(function($payload) {
                $data = json_decode($payload, true);
                if ($data === null) return false;
                return isset($data['type']) && $data['type'] === 'log' &&
                       isset($data['payload']['message']) && $data['payload']['message'] === 'Test log message' &&
                       isset($data['payload']['instanceId']) && $data['payload']['instanceId'] === 'test-instance-log';
            }))
            ->willReturn(true);

        $wsClientProp = $reflection->getProperty('wsClient');
        $wsClientProp->setAccessible(true);
        $wsClientProp->setValue(null, $mockWs);

        $loggerProp = $reflection->getProperty('logger');
        $loggerProp->setAccessible(true);
        $loggerProp->setValue(null, $this->createMock(LoggerInterface::class));

        Lescopr::sendLog('Test log message', 'info');
    }

    public function testSendMetric(): void
    {
        $reflection = new \ReflectionClass(Lescopr::class);

        $initializedProp = $reflection->getProperty('initialized');
        $initializedProp->setAccessible(true);
        $initializedProp->setValue(null, true);

        $instanceIdProp = $reflection->getProperty('instanceId');
        $instanceIdProp->setAccessible(true);
        $instanceIdProp->setValue(null, 'test-instance-metric');

        $allowedProp = $reflection->getProperty('isAllowedToSend');
        $allowedProp->setAccessible(true);
        $allowedProp->setValue(null, true);

        $mockWs = $this->createMockWebSocketClient();
        $mockWs->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $mockWs->expects($this->once())
            ->method('send')
            ->with($this->callback(function($payload) {
                $data = json_decode($payload, true);
                if ($data === null) return false;
                return isset($data['type']) && $data['type'] === 'metric' &&
                       isset($data['payload']['name']) && $data['payload']['name'] === 'test_metric' &&
                       isset($data['payload']['value']) && $data['payload']['value'] === 42 &&
                       isset($data['payload']['instanceId']) && $data['payload']['instanceId'] === 'test-instance-metric' &&
                       isset($data['payload']['tags']['environment']) && $data['payload']['tags']['environment'] === 'test';
            }))
            ->willReturn(true);

        $wsClientProp = $reflection->getProperty('wsClient');
        $wsClientProp->setAccessible(true);
        $wsClientProp->setValue(null, $mockWs);

        $loggerProp = $reflection->getProperty('logger');
        $loggerProp->setAccessible(true);
        $loggerProp->setValue(null, $this->createMock(LoggerInterface::class));

        Lescopr::sendMetric('test_metric', 42, ['environment' => 'test']);
    }
}
