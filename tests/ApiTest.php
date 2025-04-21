<?php

namespace SonnaLabs\Lescopr\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use SonnaLabs\Lescopr\Api;
use SonnaLabs\Lescopr\ApiException;

class ApiTest extends TestCase
{
    private const TEST_API_KEY = 'test-api-key';
    private const TEST_INSTANCE_ID = '34dca6fc-9901-4871-8a9f-7edd0fb70168';

    private $container = [];
    
    /**
     * Creates a mock HTTP client that returns predefined responses
     */
    private function createMockHttpClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        
        $this->container = [];
        $history = Middleware::history($this->container);
        $handlerStack->push($history);
        
        return new Client(['handler' => $handlerStack]);
    }
    
    /**
     * Test successful API key validation
     */
    public function testValidateApiKeySuccess(): void
    {
        $mockResponse = new Response(200, [], json_encode([
            'valid' => true,
            'companyName' => 'Test Company',
            'planStatus' => 'active',
            'planExpiry' => '2025-12-31'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        $result = $api->validateApiKey();
        
        $this->assertTrue($result['isValid']);
        $this->assertEquals('Test Company', $result['companyName']);
        $this->assertEquals('active', $result['planStatus']);
        $this->assertEquals('2025-12-31', $result['planExpiry']);
        
        $this->assertCount(1, $this->container);
        $request = $this->container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('auth/validate/key', $request->getUri()->getPath());
        
        $body = json_decode((string)$request->getBody(), true);
        $this->assertEquals(self::TEST_API_KEY, $body['apiKey']);
        $this->assertEquals(self::TEST_INSTANCE_ID, $body['instanceId']);
        $this->assertArrayHasKey('sdkVersion', $body);
        $this->assertArrayHasKey('timestamp', $body);
    }
    
    /**
     * Test API key validation with invalid key
     */
    public function testValidateApiKeyFailure(): void
    {
        $mockResponse = new Response(401, [], json_encode([
            'valid' => false,
            'message' => 'Invalid API key'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid API key');
        
        $api->validateApiKey();
    }
    
    /**
     * Test network error during API key validation
     */
    public function testValidateApiKeyNetworkError(): void
    {
        $mockException = new RequestException(
            'Network error',
            new Request('POST', 'auth/validate/key')
        );
        
        $mockClient = $this->createMockHttpClient([$mockException]);
        
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API key validation request failed');
        
        $api->validateApiKey();
    }
    
    /**
     * Test successful status check
     */
    public function testCheckStatusSuccess(): void
    {
        $mockResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
            'message' => 'System is operational',
            'planStatus' => 'active',
            'planExpiry' => '2025-12-31'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        $result = $api->checkStatus();
        
        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals('System is operational', $result['message']);
        $this->assertEquals('active', $result['planStatus']);
        $this->assertEquals('2025-12-31', $result['planExpiry']);
        
        $this->assertCount(1, $this->container);
        $request = $this->container[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('status/' . self::TEST_INSTANCE_ID, $request->getUri()->getPath());
        $this->assertEquals('Bearer ' . self::TEST_API_KEY, $request->getHeader('Authorization')[0]);
    }
    
    /**
     * Test error response during status check
     */
    public function testCheckStatusError(): void
    {
        $mockResponse = new Response(404, [], json_encode([
            'message' => 'Instance not found'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Instance not found');
        
        $api->checkStatus();
    }
    
    /**
     * Test successful reset notification
     */
    public function testNotifyResetSuccess(): void
    {
        $mockResponse = new Response(204);
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        $api->notifyReset();
        
        $this->assertCount(1, $this->container);
        $request = $this->container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('reset/notify', $request->getUri()->getPath());
        
        $body = json_decode((string)$request->getBody(), true);
        $this->assertEquals(self::TEST_INSTANCE_ID, $body['instanceId']);
        $this->assertArrayHasKey('timestamp', $body);
    }
    
    /**
     * Test error during reset notification
     */
    public function testNotifyResetError(): void
    {
        $mockResponse = new Response(400, [], json_encode([
            'message' => 'Invalid instance ID format'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid instance ID format');
        
        $api->notifyReset();
    }
    
    /**
     * Test JSON response decoding error
     */
    public function testJsonDecodeError(): void
    {
        $mockResponse = new Response(200, [], '{invalid:json}');
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to decode API validation response');
        
        $api->validateApiKey();
    }
    
    /**
     * Test error extraction from response
     */
    public function testErrorMessageExtraction(): void
    {
        $mockResponse = new Response(403, [], json_encode([
            'message' => 'Access denied: API key revoked',
            'errorCode' => 'AUTH_403',
            'details' => 'Please contact support for assistance'
        ]));
        
        $mockClient = $this->createMockHttpClient([$mockResponse]);
        $api = new Api(self::TEST_API_KEY, self::TEST_INSTANCE_ID, $mockClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Access denied: API key revoked');
        
        $api->checkStatus();
    }
}