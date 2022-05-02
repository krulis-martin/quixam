<?php

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

    public function getByEmail(string $email): ?User
    {
        return $this->findOneBy(["email" => $email]);
    }
}
