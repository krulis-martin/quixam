<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\Answer;
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
     * @return IQuestion structure with instantiated question data
     */
    public function instantiateQuestionData(?string &$type, $data, int $seed): IQuestion
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
            $scores[$user->getId()] = 0; // sum for each user, null if the grading is not complete
        }

        // evaluate individual questions
        $questions = $this->questions->getQuestionsOfTest($test);
        foreach ($questions as $question) {
            $enrolledId = $question->getEnrolledUser()->getId();
            $answer = $question->getLastAnswer();
            if (!$answer) {
                // make sure answer entity exist for every question and enrolled user
                $answer = new Answer($question, null); // null = empty answer (placeholder)
            }

            // find out whether the answer is correct...
            $questionData = $question->getQuestion($this->questionFactory);
            // $correct = $questionData->isAnswerCorrect($answer->getAnswer());
            $mistakes = $questionData->evaluateAnswer($answer->getAnswer());

            if ($mistakes !== null) { // null = not graded automatically
                $answer->setPoints($question->awardPointsForAnswer($mistakes, $questionData->getItemsCount()));

                // update score for the enrolled user, skip if the grading is not complete (null)
                if ($scores[$enrolledId] !== null) {
                    $scores[$enrolledId] += $answer->getPoints();
                }
            } else {
                $scores[$enrolledId] = null;
            }

            $this->answers->persist($answer);
            if ($question->getLastAnswer() === null) {
                $question->setLastAnswer($answer);
                $this->questions->persist($question);
            }
        }

        // update scores for enrolled users
        foreach ($enrolledUsers as $user) {
            if ($scores[$user->getId()] !== null) { // skip if incomplete
                $user->setScore($scores[$user->getId()]);
                $this->enrolledUsers->persist($user);
            }
        }
    }

    /**
     * Re-calculate the score for a particular enrolled user.
     * This is used when the points for a particular answer are changed.
     * @param EnrolledUser $user for which the score should be updated
     */
    public function updateScore(EnrolledUser $user): void
    {
        $score = 0;
        foreach ($user->getQuestions() as $question) {
            $answer = $question->getLastAnswer();
            if ($answer && $answer->isEvaluated()) {
                $score += $answer->getPoints();
            } else {
                $score = null; // incomplete grading
                break;
            }
        }

        $user->setScore($score);
        $this->enrolledUsers->persist($user);
    }
}
