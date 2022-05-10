<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TemplateQuestionsGroup;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<TemplateQuestionsGroup>
 */
class TemplateQuestionsGroups extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, TemplateQuestionsGroup::class);
    }
}
