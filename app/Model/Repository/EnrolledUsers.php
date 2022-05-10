<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\EnrolledUser;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<EnrolledUser>
 */
class EnrolledUsers extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, EnrolledUser::class);
    }
}
