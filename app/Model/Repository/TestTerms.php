<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TestTerm;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<TestTerm>
 */
class TestTerms extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, TestTerm::class);
    }
}
