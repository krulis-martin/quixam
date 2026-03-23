<?php

declare(strict_types=1);

namespace Quixam;

use RuntimeException;
use InvalidArgumentException;

/**
 * A REST API client for Quixam.
 */
final class RestApiClient implements IApiClient
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

        $token = $this->post('/rest/login', [], $postFields);
        if (!is_string($token) || strlen($token) < 32) {
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
        $token = $this->post('/rest/refresh');
        if (!is_string($token) || strlen($token) < 32) {
            throw new RuntimeException('Token refresh succeeded but new token is missing/invalid in response payload.');
        }

        $this->authToken = $token;
        return $token;
    }

    /**
     * Get the structure of a test template by its external ID.
     * @param string $id external ID of the test template
     * @return array<string,mixed> structured representation of the test template
     */
    public function getTemplateTest(string $id): array
    {
        return $this->get("/rest/templates/test/{$id}");
    }

    /**
     * Add a new question group to the test template or update an existing one.
     * @param string $testId external ID of the test template
     * @param string $groupId external ID of the question group
     * @param int|null $points number of points awarded for each question in this group (optional)
     * @param int|null $count number of questions selected from this group (optional)
     * @param int|null $ordering an index used for sorting the groups when the test is being assembled (optional)
     * @return array<string,bool> array with two flags ('created' and 'updated')
     *                            indicating whether the group was created or updated
     */
    public function addTemplateGroup(
        string $testId,
        string $groupId,
        ?int $points = null,
        ?int $count = null,
        ?int $ordering = null
    ): array {
        $body = [];
        if ($points !== null) {
            $body['points'] = $points;
        }
        if ($count !== null) {
            $body['count'] = $count;
        }
        if ($ordering !== null) {
            $body['ordering'] = $ordering;
        }
        return $this->post("/rest/templates/test/{$testId}/group/{$groupId}", [], $body);
    }

    /**
     * Delete a question group from the test template.
     * @param string $testId external ID of the test template
     * @param string $groupId external ID of the question group
     * @return bool true if the group was deleted, false if it did not exist
     */
    public function deleteTemplateGroup(string $testId, string $groupId): bool
    {
        return (bool)$this->delete("/rest/templates/test/{$testId}/group/{$groupId}");
    }

    /**
     * Add a new question to a question group within the test template or update an existing one.
     * @param string $testId external ID of the test template
     * @param string $groupId external ID of the question group
     * @param string $questionId external ID of the question
     * @param string $type type identifier of the question
     * @param mixed $data question data (type-specific, will be JSON-encoded)
     * @return array<string,bool> array with two flags ('created' and 'updated')
     *                            indicating whether the question was created or updated
     */
    public function addTemplateQuestion(
        string $testId,
        string $groupId,
        string $questionId,
        string $type,
        mixed $data
    ): array {
        $body = [
            'type' => $type,
            'data' => $data,
        ];
        return $this->post("/rest/templates/test/{$testId}/group/{$groupId}/question/{$questionId}", [], $body);
    }

    /**
     * Delete a question from a question group within the test template.
     * @param string $testId external ID of the test template
     * @param string $groupId external ID of the question group
     * @param string $questionId external ID of the question
     * @return bool true if the question was deleted, false if it did not exist
     */
    public function deleteTemplateQuestion(string $testId, string $groupId, string $questionId): bool
    {
        return (bool)$this->delete("/rest/templates/test/{$testId}/group/{$groupId}/question/{$questionId}");
    }

    /*
     * Private methods
     */

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

    /**
     * Prepares the HTTP headers and body for the request based on the method and content type.
     * For non-GET/DELETE requests, if the body is an array and no content type is specified,
     * it will be JSON-encoded and the Content-Type header will be set to application/json.
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param mixed $body Request body (will be modified if JSON encoding is applied)
     * @param string|null $contentType Optional content type of the request body
     * @return array<string> Array of HTTP headers to be sent with the request
     */
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
     * Performs an HTTP request to the API and returns the decoded JSON response payload.
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @param mixed $body Request body (will be JSON-encoded if it's an array and content type is not specified)
     * @param string|null $contentType Optional content type of the request body
     * @return mixed Decoded JSON response payload
     * @throws RuntimeException if the request fails or the response indicates an error
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        mixed $body = null,
        ?string $contentType = null
    ): mixed {
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

        return $data['payload'] ?? null;
    }

    /**
     * Shorthand for performing a GET request.
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @return mixed Decoded JSON response payload
     */
    private function get(string $endpoint, array $query = []): mixed
    {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * Shorthand for performing a POST request.
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @param mixed $body Request body (will be JSON-encoded)
     * @return array<string,mixed>
     */
    private function post(string $endpoint, array $query = [], ?array $body = null): mixed
    {
        return $this->request('POST', $endpoint, $query, $body);
    }

    /**
     * Shorthand for performing a PUT request.
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @param mixed $body Request body (will be JSON-encoded if no content type is specified)
     * @param string|null $contentType Optional content type of the request body
     * @return mixed Decoded JSON response payload
     */
    private function put(string $endpoint, array $query = [], mixed $body = null, ?string $contentType = null): mixed
    {
        return $this->request('PUT', $endpoint, $query, $body, $contentType);
    }

    /**
     * Shorthand for performing a DELETE request.
     * @param string $endpoint Endpoint path (relative to base URL) or absolute URL
     * @param array<string,mixed> $query Query parameters to be appended to the URL
     * @return mixed Decoded JSON response payload
     */
    private function delete(string $endpoint, array $query = []): mixed
    {
        return $this->request('DELETE', $endpoint, $query);
    }

    /*
     * Implementation of IApiClient interface
     */

    public function getTestStructure(string $testId): ?array
    {
        return $this->get("/rest/templates/test/{$testId}");
    }

    public function addGroup(string $testId, string $groupId, int $points, int $count, int $ordering): void
    {
        $this->post(
            "/rest/templates/test/{$testId}/group/{$groupId}",
            [],
            ['points' => $points, 'count' => $count, 'ordering' => $ordering]
        );
    }

    public function addQuestion(
        string $testId,
        string $groupId,
        string $questionId,
        string $type,
        string $caption_en,
        string $caption_cs,
        array $data
    ): void {
        $this->post("/rest/templates/test/{$testId}/group/{$groupId}/question/{$questionId}", [], [
            'type' => $type,
            'caption_en' => $caption_en,
            'caption_cs' => $caption_cs,
            'data' => $data,
        ]);
    }

    public function deleteGroup(string $testId, string $groupId): void
    {
        $this->delete("/rest/templates/test/{$testId}/group/{$groupId}");
    }

    public function deleteQuestion(string $testId, string $groupId, string $questionId): void
    {
        $this->delete("/rest/templates/test/{$testId}/group/{$groupId}/question/{$questionId}");
    }
}
