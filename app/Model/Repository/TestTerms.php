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
     * @param User $user
     * @param bool $onlyActive list only tests currently being written (not finished yet)
     * @return TestTerm[]
     */
    public function getTermsUserIsEnrolledFor(User $user, bool $onlyActive = false): array
    {
        $qb = $this->createQueryBuilder("tt");
        $qb->innerJoin("tt.enrolledUsers", "eu")
            ->where($qb->expr()->eq("eu.user", ":user"))
            ->andWhere($qb->expr()->isNull("tt.archivedAt"))
            ->orderBy('tt.scheduledAt');
        $qb->setParameters([ 'user' => $user->getId() ]);

        if ($onlyActive) {
            $qb->andWhere($qb->expr()->isNull("tt.finishedAt"));
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Retrieve a list of terms for which a user is registered.
     * @return TestTerm[]
     */
    public function getTermsUserIsRegisteredFor(User $user): array
    {
        $qb = $this->createQueryBuilder('tt');
        $qb->innerJoin("tt.registrations", "er")
            ->where($qb->expr()->eq("er.user", ":user"))
            ->andWhere($qb->expr()->isNull("tt.archivedAt"))
            ->orderBy('tt.scheduledAt');
        $qb->setParameters([ 'user' => $user->getId() ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * Retrieve a list of terms which the user supervises.
     * @return TestTerm[]
     */
    public function getTermsUserSupervises(User $user): array
    {
        $qb = $this->createQueryBuilder('tt');
        $qb->where(':uid MEMBER OF tt.supervisors')
            ->andWhere($qb->expr()->isNull("tt.archivedAt"))
            ->orderBy('tt.scheduledAt');
        $qb->setParameters([ 'uid' => $user->getId()]);
        return $qb->getQuery()->getResult();
    }
}
