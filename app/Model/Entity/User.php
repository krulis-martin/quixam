<?php

declare(strict_types=1);

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use Nette\Security\Passwords;
use Nette\Utils\Validators;
use InvalidArgumentException;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class User
{
    use CreateableEntity;
    use DeleteableEntity;

    public const ROLE_STUDENT = 'student';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_STUDENT,
        self::ROLE_TEACHER,
        self::ROLE_ADMIN,
    ];

    public function __construct(
        string $email,
        string $firstName,
        string $lastName,
        ?string $role
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->passwordHash = null;
        $this->isVerified = false;
        $this->createdAt = new DateTime();

        if (empty($role)) {
            $this->role = self::ROLE_STUDENT;
        } else {
            $this->role = $role;
        }
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $firstName;

    /**
     * @ORM\Column(type="string")
     */
    protected $lastName;

    public function getName()
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /**
     * @ORM\Column(type="string", unique=true)
     */
    protected $email;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVerified = false;

    public function isVerified()
    {
        return $this->isVerified;
    }

    public function setVerified($verified = true)
    {
        $this->isVerified = $verified;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $role;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $passwordHash = null;

    /**
     * @ORM\Column(type="string", nullable=true, unique=true)
     */
    protected $externalId = null;


    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     * When the last authentication or token renewal occurred.
     */
    protected $lastAuthenticationAt = null;

    /**
     * Update the last authentication time to present.
     */
    public function updateLastAuthenticationAt()
    {
        $this->lastAuthenticationAt = new DateTime();
    }

    /*
     * Password
     */

    /**
     * Change the password to the given one (the password will be hashed).
     * @param string $password New password
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     */
    public function changePassword($password, Passwords $passwordsService)
    {
        $this->setPasswordHash($passwordsService->hash($password));
    }

    /**
     * Clear user password.
     */
    public function clearPassword()
    {
        $this->setPasswordHash(null);
    }

    /**
     * Determine if password hash is empty string.
     * @return bool
     */
    public function isPasswordEmpty(): bool
    {
        return empty($this->getPasswordHash());
    }

    /**
     * Verify that the given password matches the stored password.
     * @param string $password The password given by the user
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     * @return bool
     */
    public function passwordsMatch($password, Passwords $passwordsService)
    {
        $hash = $this->getPasswordHash();
        if (empty($hash) || empty($password)) {
            return false;
        }

        if ($passwordsService->verify($password, $hash)) {
            if ($passwordsService->needsRehash($hash)) {
                $this->setPasswordHash($passwordsService->hash($password));
            }
            return true; // we have a match!
        }

        return false;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        if (!in_array($role, self::ROLES)) {
            throw new InvalidArgumentException("Attempting to set an unknown role '$role'.");
        }
        $this->role = $role;
    }

    public function getLastAuthenticationAt(): ?DateTime
    {
        return $this->lastAuthenticationAt;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $id = null): void
    {
        $this->externalId = $id;
    }
}
