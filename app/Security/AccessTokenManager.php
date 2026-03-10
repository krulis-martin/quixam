<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Exceptions\InvalidAccessTokenException;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DomainException;
use UnexpectedValueException;
use InvalidArgumentException;

class AccessTokenManager
{
    /** @var Users  Users repository */
    protected $users;

    /** @var string Name of the algorithm currently used for encrypting the signature of the token. */
    private $usedAlgorithm;

    /** @var string Verification key */
    private $verificationKey;

    /** @var int Expiration time of newly issued tokens (in seconds) */
    private $expiration;

    public function __construct(array $parameters, Users $users)
    {
        $this->users = $users;
        $this->verificationKey = Arrays::get($parameters, "verificationKey");
        if (!$this->verificationKey || strlen($this->verificationKey) < 32) {
            throw new InvalidArgumentException("AccessManager verification key is not configured or too short");
        }
        $this->expiration = Arrays::get($parameters, "expiration", 7 * 24 * 60 * 60); // one week in seconds
        $this->usedAlgorithm = Arrays::get($parameters, "usedAlgorithm", "HS256");
        JWT::$leeway = Arrays::get($parameters, "leeway", 10); // 10 seconds
    }

    public function getExpiration(): int
    {
        return $this->expiration;
    }

    /**
     * Parse and validate a JWT token and extract the payload.
     * @param string $token The potential JWT token
     * @return AccessToken The decoded payload
     * @throws InvalidAccessTokenException
     */
    public function decodeToken(string $token): AccessToken
    {
        try {
            $decodedToken = JWT::decode($token, new Key($this->verificationKey, $this->usedAlgorithm));
        } catch (DomainException $e) {
            throw new InvalidAccessTokenException($token, $e);
        } catch (UnexpectedValueException $e) {
            throw new InvalidAccessTokenException($token, $e);
        }

        if (!isset($decodedToken->sub)) {
            throw new InvalidAccessTokenException($token);
        }

        return new AccessToken($decodedToken);
    }

    /**
     * @param AccessToken $token Valid JWT payload
     * @return User
     */
    public function getUser(AccessToken $token): User
    {
        /** @var ?User $user */
        $user = $this->users->get($token->getUserId());
        if (!$user) {
            throw new InvalidAccessTokenException(
                "Forbidden Request - User does not exist",
                IResponse::S403_Forbidden
            );
        }

        return $user;
    }

    /**
     * Issue a new JWT for the user with optional explicit expiration time.
     * @param User $user
     * @param int $exp Expiration of the token in seconds
     * @param array $payload
     * @return string
     */
    public function issueToken(User $user, ?int $exp = null, array $payload = [])
    {
        if (!$exp || $exp < 10 || $exp > $this->expiration) {
            $exp = $this->expiration;
        }

        $token = new AccessToken(
            (object)array_merge(
                $payload,
                [
                    "iat" => time(),
                    "exp" => time() + $exp,
                    "sub" => $user->getId(),
                ]
            )
        );

        return $token->encode($this->verificationKey, $this->usedAlgorithm);
    }

    public function issueRefreshedToken(AccessToken $token): string
    {
        return $this->issueToken(
            $this->getUser($token),
            $token->getExpirationDuration(),
            $token->getPayloadData()
        );
    }

    /**
     * Extract the access token from the request.
     * @return AccessToken|null  The access token parsed from the HTTP request, or null if there is no access token.
     */
    public function getGivenAccessToken(IRequest $request): ?AccessToken
    {
        $accessToken = $request->getQuery("access_token");
        if ($accessToken !== null && Strings::length($accessToken) > 0) {
            return $this->decodeToken($accessToken); // the token specified in the URL is preferred
        }

        // if the token is not in the URL, try to find the "Authorization" header with the bearer token
        $authorizationHeader = $request->getHeader("Authorization");
        if ($authorizationHeader !== null) {
            $parts = Strings::split($authorizationHeader, "/ /");
            if (count($parts) === 2) {
                list($bearer, $accessToken) = $parts;
                if ($bearer === "Bearer" && !str_contains($accessToken, " ") && Strings::length($accessToken) > 0) {
                    return $this->decodeToken($accessToken);
                }
            }
        }

        return null; // there is no access token or it could not be parsed
    }
}
