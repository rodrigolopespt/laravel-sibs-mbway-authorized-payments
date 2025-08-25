<?php

namespace Rodrigolopespt\SibsMbwayAP\Api;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Exceptions\AuthenticationException;
use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;

/**
 * HTTP Client for SIBS Gateway API
 */
class Client
{
    private HttpClient $httpClient;

    private array $config;

    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $this->getBaseUrl();

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $config['defaults']['timeout'] ?? 30,
            'verify' => true,
        ]);
    }

    /**
     * Make a POST request
     */
    public function post(string $endpoint, array $data = [], ?string $transactionSignature = null): array
    {
        return $this->makeRequest('POST', $endpoint, $data, $transactionSignature);
    }

    /**
     * Make a GET request
     */
    public function get(string $endpoint, ?string $transactionSignature = null): array
    {
        return $this->makeRequest('GET', $endpoint, [], $transactionSignature);
    }

    /**
     * Make a PUT request
     */
    public function put(string $endpoint, array $data = [], ?string $transactionSignature = null): array
    {
        return $this->makeRequest('PUT', $endpoint, $data, $transactionSignature);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $endpoint, ?string $transactionSignature = null): array
    {
        return $this->makeRequest('DELETE', $endpoint, [], $transactionSignature);
    }

    /**
     * Make HTTP request to SIBS API
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], ?string $transactionSignature = null): array
    {
        $headers = $this->getHeaders($transactionSignature);
        $options = [
            'headers' => $headers,
        ];

        if (! empty($data)) {
            $options['json'] = $data;
        }

        $this->logRequest($method, $endpoint, $data, $headers);

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SibsException('Invalid JSON response from SIBS API', 0, null, [
                    'endpoint' => $endpoint,
                    'response' => $body,
                ]);
            }

            $this->logResponse($endpoint, $decoded);

            return $decoded;

        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();

            $this->logError($endpoint, $statusCode, $body);

            if ($statusCode === 401) {
                throw new AuthenticationException('Authentication failed', $statusCode, $e, [
                    'endpoint' => $endpoint,
                    'response' => $body,
                ]);
            }

            throw new SibsException('SIBS API request failed', $statusCode, $e, [
                'endpoint' => $endpoint,
                'response' => $body,
            ]);

        } catch (GuzzleException $e) {
            $this->logError($endpoint, 0, $e->getMessage());

            throw new SibsException('HTTP request failed', 0, new \Exception($e->getMessage(), $e->getCode()), [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get request headers
     */
    private function getHeaders(?string $transactionSignature = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($transactionSignature) {
            // For requests after checkout (using transaction signature)
            $headers['Authorization'] = "Digest {$transactionSignature}";
        } else {
            // For initial requests (using bearer token)
            $headers['Authorization'] = "Bearer {$this->config['credentials']['auth_token']}";
        }

        if (! empty($this->config['credentials']['client_id'])) {
            $headers['X-IBM-Client-Id'] = $this->config['credentials']['client_id'];
        }

        return $headers;
    }

    /**
     * Get base URL for current environment
     */
    private function getBaseUrl(): string
    {
        $environment = $this->config['environment'];

        if (! isset($this->config['endpoints'][$environment])) {
            throw new SibsException("Invalid environment: {$environment}");
        }

        return $this->config['endpoints'][$environment]['gateway'];
    }

    /**
     * Log outgoing request
     */
    private function logRequest(string $method, string $endpoint, array $data, array $headers): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        // Remove sensitive data from logs
        $sanitizedHeaders = $this->sanitizeHeaders($headers);
        $sanitizedData = $this->sanitizeData($data);

        Log::channel($this->getLogChannel())->info('SIBS API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'headers' => $sanitizedHeaders,
            'data' => $sanitizedData,
        ]);
    }

    /**
     * Log API response
     */
    private function logResponse(string $endpoint, array $response): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        Log::channel($this->getLogChannel())->info('SIBS API Response', [
            'endpoint' => $endpoint,
            'response' => $this->sanitizeData($response),
        ]);
    }

    /**
     * Log API error
     */
    private function logError(string $endpoint, int $statusCode, string $error): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        Log::channel($this->getLogChannel())->error('SIBS API Error', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $error,
        ]);
    }

    /**
     * Check if logging is enabled
     */
    private function shouldLog(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    /**
     * Get log channel
     */
    private function getLogChannel(): string
    {
        return $this->config['logging']['channel'] ?? 'stack';
    }

    /**
     * Remove sensitive data from headers for logging
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = $headers;

        if (isset($sanitized['Authorization'])) {
            $sanitized['Authorization'] = '[REDACTED]';
        }

        return $sanitized;
    }

    /**
     * Remove sensitive data from request/response data for logging
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'auth_token',
            'authToken',
            'transactionSignature',
            'customerPhone', // Partially sanitize phone numbers
        ];

        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                if ($field === 'customerPhone') {
                    // Keep country code and first 3 digits, hide rest
                    $phone = $sanitized[$field];
                    if (strlen($phone) > 6) {
                        $sanitized[$field] = substr($phone, 0, 6).str_repeat('*', strlen($phone) - 6);
                    }
                } else {
                    $sanitized[$field] = '[REDACTED]';
                }
            }
        }

        return $sanitized;
    }
}
