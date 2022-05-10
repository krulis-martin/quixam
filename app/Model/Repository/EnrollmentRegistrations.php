<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\EnrollmentRegistration;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<EnrollmentRegistration>
 */
class EnrollmentRegistrations extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, EnrollmentRegistration::class);
    }
}
