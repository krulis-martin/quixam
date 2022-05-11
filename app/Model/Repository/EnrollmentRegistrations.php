<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\EnrollmentRegistration;
use App\Model\Entity\User;
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

    /**
     * Try to associate all registrations which do not have the user set yet
     * using their externalIds (primarily) and their emails (as a fallback).
     * @param User $user being reassociated
     */
    public function reassociateUser(User $user)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->update(EnrollmentRegistration::class, "er")
            ->set("er.user", ":user")
            ->where($qb->expr()->isNull("er.user"));

        $emailExpr = $qb->expr()->andX(
            $qb->expr()->isNull("er.externalId"),
            $qb->expr()->eq("er.email", ":email")
        );

        if ($user->getExternalId()) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq("er.externalId", ":eid"),
                $emailExpr
            ));
            $qb->setParameter('eid', $user->getExternalId());
        } else {
            $qb->andWhere($emailExpr);
        }

        $qb->setParameter('user', $user->getId());
        $qb->setParameter('email', $user->getEmail());
        return $qb->getQuery()->getResult();
    }
}
