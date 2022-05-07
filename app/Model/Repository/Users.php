<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\User;
use DateTime;
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
}
