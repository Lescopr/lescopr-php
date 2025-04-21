<?php

namespace SonnaLabs\Lescopr\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use SonnaLabs\Lescopr\WebSocketClient;
use PHPUnit\Framework\MockObject\MockObject;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class WebSocketClientTest extends TestCase
{
    private const TEST_URL = 'wss://api.example.com/ws';

    /** @var MockObject|LoggerInterface */
    private $logger;
    /** @var MockObject|LoopInterface */
    private $loop;
    /** @var MockObject|Connector */
    private $connector;
    /** @var MockObject|WebSocket */
    private $connection;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->loop = $this->createMock(LoopInterface::class);
        $this->connector = $this->createMock(Connector::class);
        $this->connection = $this->createMock(WebSocket::class);
    }

    /**
     * Helper to create WebSocketClient with mocked dependencies
     */
    private function createClientWithMocks(): WebSocketClient
    {
        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $reflection = new \ReflectionClass(WebSocketClient::class);

        $loopProp = $reflection->getProperty('loop');
        $loopProp->setAccessible(true);
        $loopProp->setValue($client, $this->loop);

        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $connProp->setValue($client, $this->connection);

        return $client;
    }

    /**
     * Test constructor initializes properties correctly
     */
    public function testConstructor(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Client created'),
                $this->equalTo([])
            );

        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $this->assertInstanceOf(WebSocketClient::class, $client);
    }

    /**
     * Test successful connection
     */
    public function testConnectSuccess(): void
    {
        $promise = $this->createMock(PromiseInterface::class);

        $this->connector->expects($this->once())
            ->method('__invoke')
            ->with(self::TEST_URL) // Corrected: Pass argument directly
            ->willReturn($promise);

        $promise->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($onFulfilled) use ($promise) {
                $onFulfilled($this->connection);
                return $promise;
            }));

        $this->connection->expects($this->exactly(3))
            ->method('on')
            ->withConsecutive(
                [$this->equalTo('message'), $this->isType('callable')],
                [$this->equalTo('close'), $this->isType('callable')],
                [$this->equalTo('error'), $this->isType('callable')]
            );

        $this->loop->expects($this->once())->method('run');

        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $reflection = new \ReflectionClass(WebSocketClient::class);
        $loopProp = $reflection->getProperty('loop');
        $loopProp->setAccessible(true);
        $loopProp->setValue($client, $this->loop);

        // Reverted: Use separate arguments for with()
        $this->logger->expects($this->exactly(2))
            ->method('info')
             ->with(
                 $this->logicalOr(
                     $this->stringContains('[WebSocket] Attempting to connect'),
                     $this->stringContains('[WebSocket] Connection established')
                 ),
                 $this->equalTo([])
             );


        $this->markTestIncomplete('Direct testing of connect() success requires dependency injection or more complex mocking.');
    }

    /**
     * Test connection failure
     */
    public function testConnectFailure(): void
    {
        $promise = $this->createMock(PromiseInterface::class);
        $exception = new \Exception('Connection refused');

        $this->connector->expects($this->once())
            ->method('__invoke')
            ->with(self::TEST_URL) // Corrected: Pass argument directly
            ->willReturn($promise);

        $promise->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($onFulfilled, $onRejected) use ($promise, $exception) {
                $onRejected($exception);
                return $promise;
            }));

        $this->loop->expects($this->once())->method('run');

        $this->logger->expects($this->once())
            ->method('error')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Connection failed'),
                $this->equalTo(['exception' => $exception])
            );

        $client = new WebSocketClient(self::TEST_URL, $this->logger);
        $reflection = new \ReflectionClass(WebSocketClient::class);
        $loopProp = $reflection->getProperty('loop');
        $loopProp->setAccessible(true);
        $loopProp->setValue($client, $this->loop);

        $this->markTestIncomplete('Direct testing of connect() failure requires dependency injection or more complex mocking.');
    }

    /**
     * Test connection attempt when already connected
     */
    public function testConnectAlreadyConnected(): void
    {
        $client = $this->createClientWithMocks();

        $this->assertTrue($client->isConnected());

        $this->logger->expects($this->never())->method('info');
        $this->connector->expects($this->never())->method('__invoke');

        $result = $client->connect();
        $this->assertTrue($result);
    }

    /**
     * Test sending messages when connected
     */
    public function testSendSuccess(): void
    {
        $client = $this->createClientWithMocks();

        $this->assertTrue($client->isConnected());

        $this->connection->expects($this->once())
            ->method('send')
            ->with('test message'); // Corrected: Pass argument directly

        $this->loop->expects($this->once())->method('run');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Sending data'),
                $this->equalTo(['data_length' => strlen('test message')])
            );

        $result = $client->send('test message');
        $this->assertTrue($result);
    }

    /**
     * Test sending message when not connected
     */
    public function testSendNotConnected(): void
    {
        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $this->assertFalse($client->isConnected());

        $this->logger->expects($this->once())
            ->method('warning')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Cannot send data: not connected'),
                $this->equalTo([])
            );

        $this->connection->expects($this->never())->method('send');

        $result = $client->send('test message');
        $this->assertFalse($result);
    }

    /**
     * Test connection error during send
     */
    public function testSendError(): void
    {
        $client = $this->createClientWithMocks();
        $exception = new \Exception('Send failed');

        $this->assertTrue($client->isConnected());

        $this->connection->expects($this->once())
            ->method('send')
            ->with('test message') // Corrected: Pass argument directly
            ->will($this->throwException($exception));

        $this->logger->expects($this->once())
            ->method('error')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Failed to send data'),
                $this->equalTo(['exception' => $exception])
            );

        $result = $client->send('test message');
        $this->assertFalse($result);
    }

    /**
     * Test disconnection when connected
     */
    public function testDisconnect(): void
    {
        $client = $this->createClientWithMocks();

        $this->assertTrue($client->isConnected());

        $this->logger->expects($this->once())
            ->method('info')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Disconnecting'),
                $this->equalTo([])
            );

        $this->connection->expects($this->once())
            ->method('close');

        $this->loop->expects($this->once())->method('run');

        $client->disconnect();

        $reflection = new \ReflectionClass(WebSocketClient::class);
        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $this->assertNull($connProp->getValue($client));
    }

    /**
     * Test disconnection when not connected
     */
    public function testDisconnectNotConnected(): void
    {
        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $this->assertFalse($client->isConnected());

        $this->logger->expects($this->never())->method('info');
        $this->connection->expects($this->never())->method('close');

        $client->disconnect();
    }

    /**
     * Test message handler
     */
    public function testHandleMessage(): void
    {
        $client = new WebSocketClient(self::TEST_URL, $this->logger);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Message received'),
                $this->equalTo(['message_length' => strlen('test message')])
            );

        $client->handleMessage('test message');
    }

    /**
     * Test close handler
     */
    public function testHandleClose(): void
    {
        $client = $this->createClientWithMocks();

        $this->logger->expects($this->once())
            ->method('info')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Connection closed'),
                $this->equalTo(['code' => 1000, 'reason' => 'Normal closure'])
            );

        $client->handleClose(1000, 'Normal closure');

        $reflection = new \ReflectionClass(WebSocketClient::class);
        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $this->assertNull($connProp->getValue($client));
    }

    /**
     * Test error handler
     */
    public function testHandleError(): void
    {
        $client = new WebSocketClient(self::TEST_URL, $this->logger);
        $exception = new \Exception('Test error');

        $this->logger->expects($this->once())
            ->method('error')
            ->with( // Reverted: Pass constraints as separate arguments
                $this->stringContains('[WebSocket] Error occurred'),
                $this->equalTo(['exception' => $exception])
            );

        $client->handleError($exception);
    }
}