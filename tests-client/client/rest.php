<?php

declare(strict_types=1);

namespace Quixam;

use RuntimeException;
use InvalidArgumentException;

/**
 * A REST API client for Quixam.
 */
final class RestApiClient
{
    private string $baseUrl;
    private ?string $authToken;

    public function __construct(string $baseUrl, ?string $authToken = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->authToken = $authToken;
    }

    public function setAuthToken(?string $authToken): void
    {
        $this->authToken = $authToken;
    }

    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * @return array<string,mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * @return array<string,mixed>
     */
    public function post(string $endpoint, array $fields = []): array
    {
        return $this->request('POST', $endpoint, $fields);
    }

    /**
     * @return array<string,mixed>
     */
    public function put(string $endpoint, array $fields = []): array
    {
        return $this->request('PUT', $endpoint, $fields);
    }

    /**
     * @return array<string,mixed>
     */
    public function delete(string $endpoint, array $fields = []): array
    {
        return $this->request('DELETE', $endpoint, $fields);
    }

    /**
     * @return string JWT access token
     */
    public function loginAndGetToken(string $login, string $password, ?int $expirationSeconds = null): string
    {
        $postFields = [
            'login' => $login,
            'password' => $password,
        ];
        if ($expirationSeconds !== null) {
            $postFields['expiration'] = (string)$expirationSeconds;
        }

        $data = $this->request('POST', '/rest/login', $postFields);
        $token = $data['payload'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Login succeeded but token is missing/invalid in response payload.');
        }

        $this->authToken = $token;
        return $token;
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $method, string $endpoint, array $fields = []): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new InvalidArgumentException("Unsupported HTTP method '{$method}'.");
        }

        $url = $this->buildUrl($endpoint);
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        if ($method === 'GET' && $body !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $body;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Quixam tests-client',
        ];

        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method !== 'GET' && $body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request failed (cURL errno {$errno}): {$err}");
        }

        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            echo $responseBody . "\n";
            $snippet = substr($responseBody, 0, 512);
            throw new RuntimeException("Unexpected non-JSON response (HTTP {$httpCode}): {$snippet}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $data['message'] ?? $data['error'] ?? null;
            $payload = $data['payload'] ?? null;
            $details = $message ?? (is_scalar($payload) ? (string)$payload : json_encode($payload));
            throw new RuntimeException("Request failed ({$method} {$url}, HTTP {$httpCode})"
                . ($details ? ": {$details}" : '.'));
        }

        if (($data['success'] ?? null) !== true) {
            $details = $data['message'] ?? $data['error'] ?? json_encode($data);
            throw new RuntimeException('Response indicates failure: ' . (string)$details);
        }

        return $data;
    }

    private function buildUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        if ($endpoint === '') {
            return $this->baseUrl;
        }

        return $this->baseUrl . '/' . ltrim($endpoint, '/');
    }
}
