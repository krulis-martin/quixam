<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\Answer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<Answer>
 */
class Answers extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Answer::class);
    }
}
