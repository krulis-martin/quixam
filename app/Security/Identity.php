<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Entity\User;
use Nette;

class Identity implements Nette\Security\IIdentity
{
    use Nette\SmartObject;

    /** @var ?User */
    private $user;

    public const UNAUTHENTICATED_ROLE = "unauthenticated";

    public function __construct(?User $user)
    {
        $this->user = $user;
    }

    /**
     * Returns ID of the user.
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->user ? $this->user->getId() : null;
    }

    /**
     * Returns a list of roles that the user is a member of.
     * @return array
     */
    public function getRoles(): array
    {
        return $this->user ? [$this->user->getRole()] : [self::UNAUTHENTICATED_ROLE];
    }

    public function getUserData(): ?User
    {
        return $this->user;
    }

    public function getData()
    {
        $this->getUserData();
    }
}
