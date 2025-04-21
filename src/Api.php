<?php

declare(strict_types=1);

namespace SonnaLabs\Lescopr;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use PackageVersions\Versions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Api
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $instanceId;
    private string $sdkVersion;

    private const BASE_URI = 'https://api.lescopr.com/v1/';
    private const ENDPOINT_VALIDATE = 'auth/validate/key';
    private const ENDPOINT_STATUS = 'status/'; 
    private const ENDPOINT_RESET_NOTIFY = 'reset/notify';
    private const USER_AGENT_PREFIX = 'SonnaLabs-Lescopr-PHP-SDK/';

    public function __construct(
        string $apiKey,
        string $instanceId,
        ?HttpClient $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->apiKey = $apiKey;
        $this->instanceId = $instanceId;
        $this->logger = $logger ?? new NullLogger();
        $this->sdkVersion = $this->getSdkVersion();

        $this->httpClient = $httpClient ?? new HttpClient([
            'base_uri' => self::BASE_URI,
            'timeout' => 10.0,
            'headers' => $this->getDefaultHeaders()
        ]);
    }

    private function getSdkVersion(): string
    {
        try {
            $fullVersion = Versions::getVersion('sonnalabs/lescopr');
             if (preg_match('/^(\d+\.\d+\.\d+)/', $fullVersion, $matches)) {
                return $matches[1];
            }
             if (preg_match('/^(dev-[a-zA-Z0-9_-]+)/', $fullVersion, $matches)) {
                 return $matches[1];
             }
            return $fullVersion;
        } catch (\OutOfBoundsException $e) {
            $this->logger->warning('[API] Could not determine SDK version dynamically.', ['exception' => $e]);
            return 'unknown';
        }
    }

    private function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT_PREFIX . $this->sdkVersion,
        ];
    }

    /**
     * Validate API Key and Instance ID. Throws on failure.
     *
     * @return array{isValid: true, companyName: ?string, planStatus: ?string, planExpiry: ?string}
     * @throws ApiException
     */
    public function validateApiKey(): array
    {
        $this->logger->info(sprintf('[API] Validating API Key: %s... with Instance ID: %s', substr($this->apiKey, 0, 4), $this->instanceId));
        $payload = [
            'apiKey' => $this->apiKey,
            'instanceId' => $this->instanceId,
            'sdkVersion' => $this->sdkVersion,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC1036),
        ];

        try {
            $response = $this->httpClient->post(self::ENDPOINT_VALIDATE, ['json' => $payload]);
            $data = $this->decodeResponse($response);

            if ($response->getStatusCode() === 200 && isset($data['valid']) && $data['valid'] === true) {
                $this->logger->info('[API] Validation successful.');
                return [
                    'isValid' => true,
                    'companyName' => $data['companyName'] ?? null,
                    'planStatus' => $data['planStatus'] ?? null,
                    'planExpiry' => $data['planExpiry'] ?? null,
                ];
            } else {
                $this->logger->warning('[API] Validation failed by server.', [
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $data
                ]);
                $message = $data['message'] ?? 'API key validation failed by the server.';
                throw new ApiException($message);
            }
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('[API] Error during API key validation request.', ['exception_message' => $errorMessage, 'exception' => $e]);
            throw new ApiException('API key validation request failed: ' . $errorMessage, $e->getCode(), $e);
        } catch (\JsonException $e) {
             $this->logger->error('[API] Error decoding validation response.', ['exception' => $e]);
             throw new ApiException('Failed to decode API validation response.', $e->getCode(), $e);
        }
    }

    /**
     * Check the status of the instance with the backend.
     *
     * @return array{status: string, message: string, planStatus?: string, planExpiry?: string}
     * @throws ApiException
     */
    public function checkStatus(): array
    {
        $this->logger->info(sprintf('[API] Checking status for Instance ID: %s', $this->instanceId));
        $endpoint = self::ENDPOINT_STATUS . $this->instanceId;
        $options = [
            'headers' => $this->getAuthHeaders()
        ];

        try {
            $response = $this->httpClient->get($endpoint, $options);
            $data = $this->decodeResponse($response);

            if ($response->getStatusCode() === 200 && isset($data['status'])) {
                $this->logger->info('[API] Status check successful.', ['status_data' => $data]);
                return [
                    'status' => $data['status'],
                    'message' => $data['message'] ?? 'Status retrieved successfully.',
                    'planStatus' => $data['planStatus'] ?? null,
                    'planExpiry' => $data['planExpiry'] ?? null,
                ];
            } else {
                $this->logger->warning('[API] Status check failed or returned unexpected data.', [
                     'status_code' => $response->getStatusCode(),
                     'response_body' => $data
                ]);
                throw new ApiException($data['message'] ?? 'Failed to check status (invalid response).');
            }
        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('[API] Error during status check request.', ['exception_message' => $errorMessage, 'exception' => $e]);
            throw new ApiException('Status check request failed: ' . $errorMessage, $e->getCode(), $e);
        } catch (\JsonException $e) {
             $this->logger->error('[API] Error decoding status response.', ['exception' => $e]);
             throw new ApiException('Failed to decode API status response.', $e->getCode(), $e);
        }
    }

    /**
     * Notify the backend that a configuration has been reset locally.
     *
     * @throws ApiException
     */
    public function notifyReset(): void
    {
        $this->logger->info(sprintf('[API] Notifying backend of reset for Instance ID: %s', $this->instanceId));
        $payload = [
            'instanceId' => $this->instanceId,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC1036),
        ];
        $options = [
            'json' => $payload,
            'headers' => $this->getAuthHeaders()
        ];

        try {
            $response = $this->httpClient->post(self::ENDPOINT_RESET_NOTIFY, $options);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 204) {
                 $data = $this->decodeResponse($response);
                 $this->logger->warning('[API] Failed to notify backend of reset.', [
                     'status_code' => $response->getStatusCode(),
                     'response_body' => $data
                 ]);
                 throw new ApiException($data['message'] ?? 'Failed to notify backend of reset (server error).');
            }
            $this->logger->info('[API] Backend notified of reset successfully.');

        } catch (GuzzleException $e) {
            $errorMessage = $this->extractErrorMessage($e);
            $this->logger->error('[API] Error during reset notification request.', ['exception_message' => $errorMessage, 'exception' => $e]);
            throw new ApiException('Reset notification request failed: ' . $errorMessage, $e->getCode(), $e);
        } catch (\JsonException $e) {
             $this->logger->error('[API] Error encoding/decoding reset notification payload/response.', ['exception' => $e]);
             throw new ApiException('Failed to process reset notification request/response JSON.', $e->getCode(), $e);
        }
    }

    /**
     * Helper method to get specific authentication headers. Adapt as needed.
     */
    private function getAuthHeaders(): array
    {
        return array_merge($this->getDefaultHeaders(), [
             'Authorization' => 'Bearer ' . $this->apiKey
        ]);
    }

    /**
     * Decode JSON response and handle parsing errors.
     * @throws \JsonException If decoding fails.
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if (empty($body)) {
            return [];
        }
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Extract a readable error message from a GuzzleException.
     */
    private function extractErrorMessage(GuzzleException $e): string
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            $responseBody = (string) $e->getResponse()->getBody();
            if (!empty($responseBody)) {
                try {
                    $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($decoded['message']) && is_string($decoded['message'])) {
                        return $decoded['message'];
                    }
                } catch (\JsonException $jsonError) {
                    return 'Server returned non-JSON error: ' . substr($responseBody, 0, 100);
                }
            }
            return 'HTTP error ' . $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase();
        }
        return $e->getMessage();
    }
}

/**
 * Custom exception for Lescopr API related errors.
 */
class ApiException extends \RuntimeException {}