<?php

namespace App\Model\Entity;

use App\Security\Roles;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use Nette\Security\Passwords;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class User
{
    use CreateableEntity;
    use DeleteableEntity;

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
        $this->externalLogins = new ArrayCollection();

        if (empty($role)) {
            $this->role = Roles::STUDENT_ROLE;
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
     * @ORM\Column(type="string")
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
     * @ORM\OneToMany(targetEntity="ExternalLogin", mappedBy="user", cascade={"all"})
     */
    protected $externalLogins;


    /**
     * Returns true if the user entity is associated with a external login entity.
     * @return bool
     */
    public function hasExternalAccounts(): bool
    {
        return !$this->externalLogins->isEmpty();
    }

    /**
     * Return an associative array [ service => externalId ] for the user.
     * If there are multiple IDs for the same service, they are concatenated in an array.
     * If a filter is provided, only services specified on the filter list are yielded.
     * @param array|null $filter A list of services to be included in the result. Null = all services.
     * @return array
     */
    public function getConsolidatedExternalLogins(?array $filter = null)
    {
        if ($filter === []) {
            return [];  // why should we bother...
        }

        // assemble the result structure [ service => ids ]
        $res = [];
        foreach ($this->externalLogins as $externalLogin) {
            if (empty($res[$externalLogin->getAuthService()])) {
                $res[$externalLogin->getAuthService()] = [];
            }
            $res[$externalLogin->getAuthService()][] = $externalLogin->getExternalId();
        }

        // single IDs (per service) are turned into scalars
        foreach ($res as &$externalIds) {
            if (count($externalIds) === 1) {
                $externalIds = reset($externalIds);
            }
        }
        unset($externalIds);  // make sure this reference is not accidentaly reused

        // filter the list if necessary
        if ($filter !== null) {
            $resFiltered = [];
            foreach ($filter as $service) {
                if (!empty($res[$service])) {
                    $resFiltered[$service] = $res[$service];
                }
            }
            return $resFiltered;
        }

        return $res;
    }

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
        $this->role = $role;
    }

    public function getExternalLogins(): Collection
    {
        return $this->externalLogins;
    }

    public function getLastAuthenticationAt(): ?DateTime
    {
        return $this->lastAuthenticationAt;
    }
}
