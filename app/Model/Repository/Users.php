<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\User;
use App\Exceptions\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<User>
 */
class Users extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(["email" => $email]);
    }

    public function findByExternalId(string $id): ?User
    {
        return $this->findOneBy(["externalId" => $id]);
    }

    public function findByMulti(?string $id, ?string $email, ?string $externalId): ?User
    {
        $user = $id ? $this->get($id) : null;
        if ($user) {
            return $user;
        }

        $user = $email ? $this->findByEmail($email) : null;
        if ($user) {
            return $user;
        }

        return $externalId ? $this->findByExternalId($externalId) : null;
    }

    public function findByMultiOrThrow(?string $id, ?string $email, ?string $externalId): User
    {
        $user = $this->findByMulti($id, $email, $externalId);
        if (!$user) {
            throw new NotFoundException("User not found.");
        }
        return $user;
    }
}
