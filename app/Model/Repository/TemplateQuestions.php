<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<TemplateQuestion>
 */
class TemplateQuestions extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, TemplateQuestion::class);
    }

    /**
     * Internal helper function used to update links between questions and their groups
     * if new version of a group is created.
     * @param TemplateQuestionsGroup $oldGroup
     * @param TemplateQuestionsGroup $group (must alredy exist)
     */
    public function reconnectQuestions(TemplateQuestionsGroup $oldGroup, TemplateQuestionsGroup $group)
    {
        $qb = $this->createQueryBuilder('tq');
        $qb->update(TemplateQuestion::class, 'tq')
            ->set('tq.questionsGroup', ':newId')
            ->where('tq.questionsGroup = :oldId')
            ->andWhere($qb->expr()->isNull('tq.deletedAt'))
            ->setParameter('oldId', $oldGroup->getId())
            ->setParameter('newId', $group->getId());
        return $qb->getQuery()->execute();
    }
}
