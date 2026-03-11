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
    public function post(string $endpoint, array $query = [], ?array $body = null): array
    {
        return $this->request('POST', $endpoint, $query, $body);
    }

    /**
     * @return array<string,mixed>
     */
    public function put(string $endpoint, array $query = [], mixed $body = null, ?string $contentType = null): array
    {
        return $this->request('PUT', $endpoint, $query, $body, $contentType);
    }

    /**
     * @return array<string,mixed>
     */
    public function delete(string $endpoint, array $query = []): array
    {
        return $this->request('DELETE', $endpoint, $query);
    }

    /**
     * Logs in with the given credentials and returns the JWT access token.
     * @param string $login User login (local username)
     * @param string $password User password
     * @param int|null $expirationSeconds Optional requested token expiration length in seconds.
     *                                    If null, the server default will be used.
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

        $data = $this->post('/rest/login', [], $postFields);
        $token = $data['payload'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Login succeeded but token is missing/invalid in response payload.');
        }

        $this->authToken = $token;
        return $token;
    }

    /**
     * Refreshes the current authentication token and returns the new token.
     * The current token must be valid and not expired, otherwise an exception will be thrown.
     * @return string New JWT access token
     */
    public function refreshToken(): string
    {
        $data = $this->post('/rest/refresh');
        $token = $data['payload'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Token refresh succeeded but new token is missing/invalid in response payload.');
        }

        $this->authToken = $token;
        return $token;
    }

    /**
     * Builds the full URL for the given endpoint and query parameters.
     * If the endpoint is an absolute URL, it is returned as is.
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @return string Full URL for the request
     */
    private function buildUrl(string $endpoint, array $query = []): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        if ($endpoint === '') {
            return $this->baseUrl;
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    private function prepareHeadersAndBody(string $method, mixed &$body, ?string $contentType): array
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: Quixam tests-client',
        ];
        if ($this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        if ($method !== 'GET' && $method !== 'DELETE') {
            if ($body !== null && !$contentType) {
                $contentType = 'application/json';
                $body = json_encode($body);
            }
        } else {
            $contentType = null;
            $body = null;
        }

        if ($contentType) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        return $headers;
    }

    /**
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        mixed $body = null,
        ?string $contentType = null
    ): array {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new InvalidArgumentException("Unsupported HTTP method '{$method}'.");
        }

        $url = $this->buildUrl($endpoint, $query);
        $headers = $this->prepareHeadersAndBody($method, $body, $contentType);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
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
}
