<?php

declare(strict_types=1);

namespace Quixam;

use RuntimeException;
use InvalidArgumentException;
use DateTime;

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

    /*
     * Authentication-related methods
     */

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

    /*
     * Additional methods that will be added to the IApiClient in the future (now available only via RestApiClient)
     */

    /**
     * Fetch all terms associated with a specific test template (manageable by the user).
     * @param string|null $testId external ID of the test template (optional)
     * @return array list of terms
     */
    public function getTerms(?string $testId = null): array
    {
        return $this->get('/rest/terms', $testId ? ['testId' => $testId] : []);
    }

    /**
     * Add a new term based on the given test template. If a term with the same external ID already exists,
     * it will be updated instead. The term must not have started or been archived.
     * @param string $testId external ID of the test template
     * @param string $externalId external ID of the term
     * @param string|null $location where the term is held (optional)
     * @param DateTime|null $scheduledAt when the term is scheduled (optional)
     * @param array $supervisors list of supervisors (externalIDs or emails) for the term (optional)
     * @param string|null $noteEn English note for the term (optional)
     * @param string|null $noteCs Czech note for the term (optional)

     */
    public function addTerm(
        string $testId,
        string $externalId,
        ?string $location = null,
        ?DateTime $scheduledAt = null,
        array $supervisors = [],
        ?string $noteEn = null,
        ?string $noteCs = null
    ): void {
        foreach ($supervisors as &$s) {
            $s = [(filter_var($s, FILTER_VALIDATE_EMAIL) ? 'email' : 'externalId') => $s];
        }
        $body = [
            'externalId' => $externalId,
            'location' => $location,
            'scheduledAt' => $scheduledAt?->getTimestamp(),
            'supervisors' => $supervisors,
        ];
        if ($noteEn !== null) {
            $body['note']['en'] = $noteEn;
        }
        if ($noteCs !== null) {
            $body['note']['cs'] = $noteCs;
        }

        $this->post("/rest/terms/{$testId}", [], $body);
    }

    /**
     * Remove a term by its ID.
     * @param string $termId ID of the term to remove
     */
    public function removeTerm(string $termId): void
    {
        $this->delete("/rest/term/{$termId}");
    }

    /**
     * Register users for a term.
     * @param string $termId ID of the term
     * @param array $users list of users to register; each user is identified by an associative array with
     *                     at least one of the keys "id", "externalId", or "email" is present
     */
    public function registerUsers(string $termId, array $users): void
    {
        $this->post("/rest/term/{$termId}/users", [], ['users' => $users]);
    }
}
