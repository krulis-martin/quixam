<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\EnrolledUser;
use App\Model\Entity\Question;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\Questions;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
use App\Model\Repository\TestTerms;
use Nette\SmartObject;

class TestOrchestrator
{
    use SmartObject;

    /** @var Questions */
    private $questions;

    /** @var QuestionFactory */
    private $questionFactory;

    public function __construct(Questions $questions, QuestionFactory $questionFactory)
    {
        $this->questions = $questions;
        $this->questionFactory = $questionFactory;
    }

    /**
     * Create a set of question instances for given enrolled user.
     * Nothing is returned, the questions are persisted in db.
     * @param EnrolledUser $user enrolled for a particular test
     */
    public function instantiate(EnrolledUser $user): void
    {
        Random::setSeed($user->getSeed());

        $test = $user->getTest();
        $testTemplate = $test->getTemplate();

        // select questions for the test
        $templateQuestions = [];
        foreach ($testTemplate->getQuestionGroups() as $templateGroup) {
            $candidates = $templateGroup->getQuestions()->toArray();
            foreach (Random::selectRandomSubset($candidates, $templateGroup->getSelectCount()) as $tq) {
                $templateQuestions[] = $tq;
            }
        }

        // instantiate each question form its template
        foreach ($templateQuestions as $idx => $templateQuestion) {
            $ordering = $idx + 1;
            $questionData = $this->questionFactory->create($templateQuestion->getType());
            $questionData->instantiate($templateQuestion->getData(), $user->getSeed() + $ordering);

            $question = new Question(
                $user,
                $templateQuestion->getQuestionsGroup(),
                $templateQuestion,
                $ordering,
                $questionData
            );
            $this->questions->persist($question);
        }
    }
}
