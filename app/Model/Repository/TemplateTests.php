<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\User;
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

    public function getTemplatesUserOwns(User $user): array
    {
        $qb = $this->createQueryBuilder("tt");
        $qb->where($qb->expr()->isMemberOf(':uid', 'tt.owners'))
            ->orderBy('tt.courseId')
            ->addOrderBy('tt.externalId')
            ->addOrderBy('tt.createdAt');
        $qb->setParameters(['uid' => $user->getId()]);
        return $qb->getQuery()->getResult();
    }
}
