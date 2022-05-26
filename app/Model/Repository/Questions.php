<?php

declare(strict_types=1);

namespace App\Model\Repository;

use App\Model\Entity\Question;
use App\Model\Entity\TestTerm;
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

    /**
     * Retrieve a list of questions of all users enrolled for a particular test.
     * @param TestTerm $test for which the questions are retrieved
     * @return Question[]
     */
    public function getQuestionsOfTest(TestTerm $test): array
    {
        $qb = $this->createQueryBuilder('q');
        $qb->innerJoin("q.enrolledUser", "eu")->where($qb->expr()->eq("eu.test", ":test"));
        $qb->setParameter('test', $test->getId());
        return $qb->getQuery()->getResult();
    }

    public function getQuestionsOfTestSorted(TestTerm $test): array
    {
        $questions = $this->getQuestionsOfTest($test);
        $result = [];
        foreach ($questions as $question) {
            $result[$question->getEnrolledUser()->getUser()->getId()][$question->getOrdering()] = $question;
        }
        foreach ($result as &$userQuestions) {
            ksort($userQuestions);
        }
        return $result;
    }
}
