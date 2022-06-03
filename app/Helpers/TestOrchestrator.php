<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\EnrolledUser;
use App\Model\Entity\Question;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Model\Repository\Answers;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\Questions;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
use App\Model\Repository\TestTerms;
use Nette\SmartObject;

class TestOrchestrator
{
    use SmartObject;

    /** @var Answers */
    private $answers;

    /** @var EnrolledUsers */
    private $enrolledUsers;

    /** @var Questions */
    private $questions;

    /** @var QuestionFactory */
    private $questionFactory;

    public function __construct(
        Answers $answers,
        EnrolledUsers $enrolledUsers,
        Questions $questions,
        QuestionFactory $questionFactory
    ) {
        $this->answers = $answers;
        $this->enrolledUsers = $enrolledUsers;
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
        $maxScore = 0;
        foreach ($templateQuestions as $idx => $templateQuestion) {
            $ordering = $idx + 1;
            $type = $templateQuestion->getType();
            if ($type) {
                // regular (static) question
                $questionData = $this->questionFactory->create($templateQuestion->getType());
                $questionData->instantiate($templateQuestion->getData(), $user->getSeed() + $ordering);
            } else {
                // no type => question generated dynamically
                $dynamicQuestion = new DynamicQuestion($templateQuestion->getData(), $this->questionFactory);
                $dynamicQuestion->generate();
                $type = $dynamicQuestion->getType();
                $questionData = $dynamicQuestion->getQuestion();
            }

            $question = new Question(
                $user,
                $templateQuestion->getQuestionsGroup(),
                $templateQuestion,
                $ordering,
                $questionData,
                $type
            );
            $maxScore += $question->getPoints();
            $this->questions->persist($question);
        }

        $user->setMaxScore($maxScore);
        $this->enrolledUsers->persist($user);
    }

    /**
     * Evaluate all last answers of a particular test.
     * The method does not return anything, it only makes updated to the database.
     * @param TestTerm $test which is being evaluated
     */
    public function evlauate(TestTerm $test): void
    {
        // prepare score table for each user
        $enrolledUsers = $test->getEnrolledUsers();
        $scores = [];
        foreach ($enrolledUsers as $user) {
            $scores[$user->getId()] = 0;
        }

        // evaluate individual questions
        $questions = $this->questions->getQuestionsOfTest($test);
        foreach ($questions as $question) {
            $answer = $question->getLastAnswer();
            if ($answer) {
                $questionData = $question->getQuestion($this->questionFactory);
                $correct = $questionData->isAnswerCorrect($answer->getAnswer());
                $answer->setPoints($correct ? $question->getPoints() : 0);
                $scores[$question->getEnrolledUser()->getId()] += $answer->getPoints();
                $this->answers->persist($answer);
            }
        }

        // update scores for enrolled users
        foreach ($enrolledUsers as $user) {
            $user->setScore($scores[$user->getId()]);
            $this->enrolledUsers->persist($user);
        }
    }
}
