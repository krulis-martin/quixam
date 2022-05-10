<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\Question;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<Question>
 */
class Questions extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Question::class);
    }
}
