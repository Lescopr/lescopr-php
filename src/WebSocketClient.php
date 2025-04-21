<?php

declare(strict_types=1);

namespace SonnaLabs\Lescopr;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;


class WebSocketClient
{
    private string $url;
    private LoggerInterface $logger;
    private ?WebSocket $connection = null;
    private ?LoopInterface $loop = null;
    private bool $isConnecting = false;

    public function __construct(string $url, ?LoggerInterface $logger = null)
    {
        $this->url = $url;
        $this->logger = $logger ?? new NullLogger();
        $this->logger->info("[WebSocket] Client created for URL: {$this->url}");
    }

    /**
     * Establishes connection to WebSocket server
     */
    public function connect(): bool
    {
        if ($this->isConnected() || $this->isConnecting) {
            return true;
        }
        
        $this->isConnecting = true;
        $this->logger->info("[WebSocket] Attempting to connect...");
        
        try {
            $this->loop = Loop::get();
            $connector = new Connector($this->loop);
            
            $connector($this->url)->then(
                function (WebSocket $conn) {
                    $this->connection = $conn;
                    $this->isConnecting = false;
                    $this->logger->info("[WebSocket] Connection established");
                    
                    $conn->on('message', function (MessageInterface $msg) {
                        $this->handleMessage((string)$msg);
                    });
                    
                    $conn->on('close', function ($code = null, $reason = null) {
                        $this->handleClose($code ?? 0, $reason ?? 'Unknown reason');
                    });
                    
                    $conn->on('error', function (\Exception $e) {
                        $this->handleError($e);
                    });
                },
                function (\Exception $e) {
                    $this->isConnecting = false;
                    $this->logger->error("[WebSocket] Connection failed", ['exception' => $e]);
                }
            );
            
            $this->loop->run();
            return true;
            
        } catch (\Throwable $e) {
            $this->isConnecting = false;
            $this->logger->error("[WebSocket] Connection failed", ['exception' => $e]);
            return false;
        }
    }

    /**
     * Sends data to the WebSocket server
     */
    public function send(string $data): bool
    {
        if (!$this->isConnected()) {
            $this->logger->warning("[WebSocket] Cannot send data: not connected");
            return false;
        }

        $this->logger->debug("[WebSocket] Sending data", ['data_length' => strlen($data)]);
        
        try {
            $this->connection->send($data);
            if ($this->loop) {
                $this->loop->run();
            }
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("[WebSocket] Failed to send data", ['exception' => $e]);
            return false;
        }
    }

    /**
     * Closes WebSocket connection
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->logger->info("[WebSocket] Disconnecting...");
            try {
                $this->connection->close();
                if ($this->loop) {
                    $this->loop->run();
                }
                $this->connection = null;
            } catch (\Throwable $e) {
                $this->logger->error("[WebSocket] Error during disconnection", ['exception' => $e]);
            }
        }
    }

    /**
     * Checks if connection is active
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Handles incoming WebSocket messages
     */
    public function handleMessage(string $message): void
    {
        $this->logger->debug("[WebSocket] Message received", ['message_length' => strlen($message)]);
    }

    /**
     * Handles connection closure
     */
    public function handleClose(int $code, string $reason): void
    {
        $this->logger->info("[WebSocket] Connection closed", ['code' => $code, 'reason' => $reason]);
        $this->connection = null;
    }

    /**
     * Handles WebSocket errors
     */
    public function handleError(\Throwable $error): void
    {
        $this->logger->error("[WebSocket] Error occurred", ['exception' => $error]);
    }
}