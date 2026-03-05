<?php

declare(strict_types=1);

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;
use Firebase\JWT\JWT;
use stdClass;
use InvalidArgumentException;

class AccessToken
{
    /** @var string The subject (user ID) */
    private $sub;

    /** @var int Issued at time (timestamp) */
    private $iat;

    /** @var int Expiration time (timestamp) */
    private $exp;

    /** @var stdClass Payload of the token */
    private $payload;

    /**
     * Create a wrapper for a given JWT payload.
     * @param object $payload The decoded payload of the token
     */
    public function __construct($payload)
    {
        foreach (["sub", "iat", "exp"] as $requiredClaim) {
            if (empty($payload->$requiredClaim)) {
                throw new InvalidAccessTokenException(
                    "Missing the required '$requiredClaim' parameter of the token payload."
                );
            }
        }

        $this->sub = (string)$payload->sub;
        $this->iat = (int)$payload->iat;
        $this->exp = (int)$payload->exp;

        $this->payload = $payload;
    }

    /**
     * Extract user's id from the token payload
     * @return string
     * @throws InvalidAccessTokenException
     */
    public function getUserId(): string
    {
        if ($this->sub === null) {
            throw new InvalidAccessTokenException("Missing the required 'sub' parameter of the token payload.");
        }

        return $this->sub;
    }

    public function getIssuedAt(): int
    {
        return $this->iat;
    }

    public function getExpirationAt(): int
    {
        return $this->exp;
    }

    public function getExpirationDuration(): int
    {
        return $this->exp - $this->iat;
    }

    public function getPayloadData(): array
    {
        return (array)$this->payload;
    }

    /**
     * Access any claim of the payload.
     * @param string $key
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getPayload($key)
    {
        if (!isset($this->payload->$key)) {
            throw new InvalidArgumentException("The payload of the access token does not contain claim '$key'");
        }

        return $this->payload->$key;
    }

    /**
     * Access any claim of the payload. If the claim is not present, return a default value.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPayloadOrDefault($key, $default)
    {
        if (!isset($this->payload->$key)) {
            return $default;
        }

        return $this->payload->$key;
    }

    public function encode(string $verificationKey, string $usedAlgorithm): string
    {
        return JWT::encode((array)$this->payload, $verificationKey, $usedAlgorithm);
    }
}
