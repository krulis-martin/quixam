<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Entity\User;
use Psr\Log\LoggerInterface;
use phpCAS;
use DateTime;
use DateTimeInterface;
use RuntimeException;

class CasAuthenticator implements IExternalAuthenticator
{
    /** @var LoggerInterface **/
    private $logger;

    // configuration
    private $server = null;
    private $port = null;
    private $uri = null;
    private $certificate = null;

    /** @var string|null */
    private $id = null;

    /** @var string|null */
    private $firstName = null;

    /** @var string|null */
    private $lastName = null;

    /** @var string|array|null */
    private $mail = null;

    /** @var DateTime|null */
    private $authenticatedAt = null;

    private $affiliations = null;


    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->server = $config['server'] ?? null;
        $this->port = (int)($config['port'] ?? 443);
        $this->uri = $config['uri'] ?? '/cas';
        $this->certificate = $config['certificate'] ?? null;
    }

    private function loadAttributes($attributes): bool
    {
        if (!$attributes || !is_array($attributes)) {
            return false; // the authentication must have failed
        }

        $this->logger->debug("Loaded attributes: " . json_encode($attributes));

        $stringAttributes = [
            'cunipersonalid' => 'id',
            'mail' => 'mail',
            'givenname' => 'firstName',
            'sn' => 'lastName',
            'edupersonscopedaffiliation' => 'affiliations',
        ];

        // load mandatory string attributes
        $errors = [];
        foreach ($stringAttributes as $attr => $prop) {
            if (!array_key_exists($attr, $attributes) || !$attributes[$attr]) {
                $errors[] = $attr;
            } else {
                $this->$prop = $attributes[$attr];
            }
        }

        // aditional check for email format
        $this->mail = is_array($this->mail) ? $this->mail : [ $this->mail ];
        foreach ($this->mail as $mail) {
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'mail';
                break;
            }
        }

        // authentication timestamp (if present)
        $date = $attributes['authenticationDate'] ?? null;
        if ($date) {
            $this->authenticatedAt = DateTime::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $date);
        }

        // make sure affiliations are stored as an array
        $this->affiliations = is_array($this->affiliations) ? $this->affiliations : [ $this->affiliations ];

        if ($errors) {
            $this->logger->warning("Attributes validation failed: " . join(', ', $errors));
        }

        return count($errors) === 0;
    }


    /*
     * IExternalAuthenticator
     */

    public function initialize(): void
    {
        $base = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
        phpCAS::client(CAS_VERSION_3_0, $this->server, $this->port, $this->uri, $base);

        if ($this->certificate) {
            $this->logger->debug("Using $this->certificate PEM chain to verify CAS server.");
            $certificate = __DIR__ . '/../../../' . $this->certificate;
            if (!is_file($certificate) || !is_readable($certificate)) {
                throw new RuntimeException("Given certificate path is either not a file or is not readable.");
            }
            phpCAS::setCasServerCACert($certificate, true);
        } else {
            phpCAS::setNoCasServerValidation();
            $this->logger->info("CAS certificate chain is not validated.");
        }
    }

    public function authenticate(): bool
    {
        phpCAS::forceAuthentication();
        return $this->loadAttributes(phpCAS::getAttributes());
    }

    public function isFresh(): bool
    {
        $diff = $this->authenticatedAt ? (new DateTime())->getTimestamp() - $this->authenticatedAt->getTimestamp() : 0;
        return $diff < 10; // less than 10s has passed
    }

    public function getUserId(): string
    {
        return $this->id;
    }

    public function getUserFirstName(): string
    {
        return $this->firstName;
    }

    public function getUserLastName(): string
    {
        return $this->lastName;
    }

    public function getUserEmails(): array
    {
        return $this->mail ?? [];
    }

    public function getUserSuggestedRole(): string
    {
        if (!$this->affiliations) {
            return '';
        }

        $keywords = [
            'student' => User::ROLE_STUDENT,
            'faculty' => User::ROLE_TEACHER,
            'employee' => User::ROLE_TEACHER,
        ];

        foreach ($keywords as $keyword => $role) {
            foreach ($this->affiliations as $affiliation) {
                if (preg_match("/$keyword/", $affiliation)) {
                    return $role;
                }
            }
        }
        return '';
    }

    public function logout(): void
    {
        phpCAS::logout();
    }
}
