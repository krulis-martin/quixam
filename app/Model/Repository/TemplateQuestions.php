<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\TemplateQuestion;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<TemplateQuestion>
 */
class TemplateQuestions extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, TemplateQuestion::class);
    }
}
