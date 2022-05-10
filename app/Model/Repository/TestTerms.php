<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Model\Entity\EnrollmentRegistration;
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

    /**
     * Retrieve a list of terms for which a user is enrolled for.
     * @return TestTerm[]
     */
    public function getTermsUserIsEnrolledFor(User $user): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select("tt")->from(TestTerm::class, "tt")->innerJoin("tt.enrolledUsers", "eu")
            ->where($qb->expr()->eq("eu.user", ":user"))
            ->andWhere($qb->expr()->isNull("tt.archivedAt"))
            ->orderBy('tt.finishedAt, tt.startedAt, tt.scheduledAt, tt.createdAt');
        $qb->setParameters([ 'user' => $user->getId() ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * Retrieve a list of terms for which a user is registered.
     * @return TestTerm[]
     */
    public function getTermsUserIsRegisteredFor(User $user): array
    {
        $qb = $this->em->createQueryBuilder('tt');
        $qb->select("tt")->from(TestTerm::class, "tt")->innerJoin("tt.registrations", "er")
            ->where($qb->expr()->eq("er.user", ":user"))
            ->andWhere($qb->expr()->isNull("tt.archivedAt"))
            ->orderBy('tt.finishedAt, tt.startedAt, tt.scheduledAt, tt.createdAt');
        $qb->setParameters([ 'user' => $user->getId() ]);
        return $qb->getQuery()->getResult();
    }
}
