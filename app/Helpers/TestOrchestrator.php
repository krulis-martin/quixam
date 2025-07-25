<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\EnrolledUser;
use App\Model\Entity\Question;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TestTerm;
use App\Model\Repository\Answers;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\Questions;
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
     * Instantiate/generate question data.
     * @param string|null $type passed by a reference since dynamic questions will change it
     * @param mixed $data parsed JSON data from the question template
     * @param int $seed random initializer
     * @return mixed structure with instantiated question data
     */
    public function instantiateQuestionData(?string &$type, $data, int $seed)
    {
        if ($type) {
            // regular (static) question
            $questionData = $this->questionFactory->create($type);
            $questionData->instantiate($data, $seed);
        } else {
            // no type => question generated dynamically
            $dynamicQuestion = new DynamicQuestion($data, $this->questionFactory);
            $dynamicQuestion->generate($seed);
            $type = $dynamicQuestion->getType();
            $questionData = $dynamicQuestion->getQuestion();
        }
        return $questionData;
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
            $candidates = $templateGroup->getQuestions()->filter(function (TemplateQuestion $tq) {
                return !$tq->isDisabled(); // disabled templates are removed from candidate list
            })->toArray();
            foreach (Random::selectRandomSubset($candidates, $templateGroup->getSelectCount()) as $tq) {
                $templateQuestions[] = $tq;
            }
        }

        // instantiate each question form its template
        $maxScore = 0;
        foreach ($templateQuestions as $idx => $templateQuestion) {
            $ordering = $idx + 1;
            $type = $templateQuestion->getType();
            $data = $this->instantiateQuestionData($type, $templateQuestion->getData(), $user->getSeed() + $ordering);
            $question = new Question(
                $user,
                $templateQuestion->getQuestionsGroup(),
                $templateQuestion,
                $ordering,
                $data,
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
    public function evaluate(TestTerm $test): void
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
