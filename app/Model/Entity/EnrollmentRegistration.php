<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;

/**
 * A registration of an user for enrollment.
 * Registration is handled separately since it may be done by supervisors before the user actually registers.
 * ExternalIDs and emails can be used to identify users (beside the actual user IDs).
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"test_id", "user_id"})})
 */
class EnrollmentRegistration
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TestTerm")
     */
    protected $test;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $email = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $externalId = null;

    /**
     * @param TestTerm $test
     * @param User|null $user
     */
    public function __construct(TestTerm $test, User $user = null)
    {
        $this->createdAt = new DateTime();
        $this->test = $test;
        $this->user = $user;
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getTest(): TestTerm
    {
        return $this->test;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }
}
