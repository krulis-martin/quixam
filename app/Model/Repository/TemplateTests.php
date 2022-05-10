<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TemplateTest;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<TemplateTest>
 */
class TemplateTests extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, TemplateTest::class);
    }
}
