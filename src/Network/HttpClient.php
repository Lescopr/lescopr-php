<?php

declare(strict_types=1);

namespace Lescopr\Network;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Simple HTTP client wrapping Guzzle.
 * Used by Lescopr core to call api.lescopr.com.
 */
class HttpClient
{
    private Client $client;
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
        $this->client  = new Client([
            'timeout'         => $timeout,
            'connect_timeout' => 5,
            'http_errors'     => false,
            'headers'         => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'lescopr-php-sdk/0.1.0',
            ],
        ]);
    }

    /**
     * HTTP POST — returns ['ok' => bool, 'data' => array|null, 'status' => int, 'error' => string|null]
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     * @return array{ok: bool, data: array<string, mixed>|null, status: int, error: string|null}
     */
    public function post(string $url, array $body, array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'json'    => $body,
                'headers' => $headers,
            ]);

            $status  = $response->getStatusCode();
            $raw     = (string) $response->getBody();
            $decoded = null;

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decoded = ['raw' => $raw];
            }

            return [
                'ok'     => $status >= 200 && $status < 300,
                'data'   => $decoded,
                'status' => $status,
                'error'  => $status >= 400 ? ($decoded['message'] ?? $decoded['detail'] ?? "HTTP $status") : null,
            ];
        } catch (GuzzleException $e) {
            return [
                'ok'     => false,
                'data'   => null,
                'status' => 0,
                'error'  => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'data'   => null,
                'status' => 0,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * HTTP GET
     *
     * @param array<string, string> $headers
     * @return array{ok: bool, data: array<string, mixed>|null, status: int, error: string|null}
     */
    public function get(string $url, array $headers = []): array
    {
        try {
            $response = $this->client->get($url, ['headers' => $headers]);
            $status   = $response->getStatusCode();
            $raw      = (string) $response->getBody();

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decoded = ['raw' => $raw];
            }

            return [
                'ok'     => $status >= 200 && $status < 300,
                'data'   => $decoded,
                'status' => $status,
                'error'  => $status >= 400 ? "HTTP $status" : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'data'   => null,
                'status' => 0,
                'error'  => $e->getMessage(),
            ];
        }
    }
}

