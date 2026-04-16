<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\Answers;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Repository\Questions;
use App\Model\Repository\TestTerms;
use App\Model\Entity\Answer;
use App\Model\Entity\EnrolledUser;
use App\Model\Entity\Question;
use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Helpers\QuestionFactory;
use App\Helpers\TestOrchestrator;
use Nette\Bridges\ApplicationLatte\LatteFactory;

final class TestPresenter extends AuthenticatedPresenter
{
    /** @var Answers @inject */
    public $answers;

    /** @var EnrolledUsers @inject */
    public $enrolledUsers;

    /** @var EnrollmentRegistrations @inject */
    public $enrollmentRegistrations;

    /** @var Questions @inject */
    public $questions;

    /** @var QuestionFactory @inject */
    public $questionFactory;

    /** @var TestTerms @inject */
    public $testTerms;

    /** @var LatteFactory @inject */
    public $latteFactory;

    /** @var TestOrchestrator @inject */
    public $testOrchestrator;

    /** @persistent */
    public $question = null;

    /** @persistent */
    public $selectedUser = null;

    /**
     * Get the test term entity based on the presenter's parameters and perform additional checks.
     * @param string $id test term ID
     * @param bool|null $finished if true, the test must be finished; if false, the test must not be finished;
     *                            if null, no check is performed
     * @return TestTerm
     */
    private function getTest(string $id, ?bool $finished = null): TestTerm
    {
        $test = $this->testTerms->get($id);
        if (!$test) {
            $this->error("Test $id does not exist.", 404);
        }

        if ($finished !== null) {
            if (!$finished && $test->getFinishedAt() !== null) {
                $this->finalizePostError($this->translator->translate('locale.test.error.alreadyFinished'));
            }
            if ($finished && $test->getFinishedAt() === null) {
                $this->finalizePostError($this->translator->translate('locale.test.error.notYetFinished'));
            }
        }

        return $test;
    }

    /**
     * Return enrolled user entity based on the presenter's parameters.
     * @param string $id test term ID
     * @return ?EnrolledUser
     */
    private function getEnrolledUser(string $id): ?EnrolledUser
    {
        $userId = $this->user->getId();
        if ($this->selectedUser && $this->user->getRole() !== User::ROLE_STUDENT) {
            $userId = $this->selectedUser;
        }
        return $this->enrolledUsers->findOneBy(['test' => $id, 'user' => $userId]);
    }

    /**
     * Get the question entity based on the presenter's parameters. Terminate if no question is selected.
     * @return Question
     */
    private function getSelectedQuestion(): Question
    {
        $question = $this->question ? $this->questions->get($this->question) : null;
        if (!$question) {
            $this->finalizePostError("Unable to proceed since no question is selected.");
        }
        return $question;
    }

    /**
     * Get an index of "selected" question. If the question is selected explicitly by a query parameter,
     * or the index of the first question that do not have an answer.
     * @param array $questions all question of enrolled user for selected test
     * @param bool $testFinished if true, it targets first answered question instead (to better display results)
     * @return int index of the selected question, count($questions) of no question is selected
     */
    private function getSelectedQuestionIndex(array $questions, bool $testFinished): int
    {
        $res = count($questions);
        foreach ($questions as $idx => $question) {
            if ($this->question && $this->question === $question->getId()) {
                return $idx; // explicit selection by a parameter
            }

            if ($testFinished && $idx < $res && $question->getLastAnswer()) {
                $res = $idx; // test finished -> looking for first answered question
            }
            if (!$testFinished && $idx < $res && $question->getLastAnswer() === null) {
                $res = $idx; // not finished -> looking for first unanswered question
            }
        }

        return ($testFinished && $res >= count($questions)) ? 0 : $res;
    }

    /**
     * Automatically invoked permissions check for the Default action (and its signals).
     * @return bool true if the user has access to display default view
     */
    public function checkDefault(string $id): bool
    {
        if ($this->user->getRole() === User::ROLE_STUDENT) {
            // a student must be enrolled to see the test, which has already started
            $enrolled = $this->getEnrolledUser($id);
            if ($enrolled === null || $enrolled->getTest()->getStartedAt() === null) {
                return false;
            }

            if ($enrolled->getTest()->getFinishedAt() !== null) {
                // student cannot access old tests when writing a test
                return !$this->testTerms->getTermsUserIsEnrolledFor($this->user, true); // true = only active
            }

            return true;
        }

        if ($this->user->getRole() === User::ROLE_TEACHER) {
            // teacher must be a supervisor
            $test = $this->testTerms->get($id);
            return $test && $test->getSupervisors()->contains($this->user);
        }

        return false;
    }

    /**
     * Signal handler for AJAX call that saves the question answer.
     */
    public function handleSave(string $id): void
    {
        $this->getTest($id, false); // false = test must not be finished yet

        $enrolledUser = $this->enrolledUsers->findOneBy(['test' => $id, 'user' => $this->user->getId()]);
        if (!$enrolledUser || $enrolledUser->isLocked()) {
            $this->finalizePostError($this->translator->translate('locale.test.error.locked'));
        }

        $question = $this->getSelectedQuestion();
        $questionData = $question->getQuestion($this->questionFactory);

        // get the answer and validate it
        $req = $this->getRequest();
        $answer = $questionData->processAnswerSubmit($req->getPost());
        if (!$questionData->isAnswerValid($answer)) {
            $this->finalizePostError($this->translator->translate('locale.test.error.invalidAnswer'));
        }

        // save the answer
        $answerEntity = new Answer($question, $answer, $this->getHttpRequest()->getRemoteAddress() ?? '');
        $this->answers->persist($answerEntity);

        $question->setLastAnswer($answerEntity);
        $this->questions->persist($question);

        $next = $req->getPost('nextQuestion');
        $this->finalizePost($this->link('default', ['id' => $id, 'question' => $next ? $next : null]));
    }

    /**
     * Signal handler for the self-lock button.
     */
    public function handleLockSelf(string $id): void
    {
        $test = $this->getTest($id);
        if (!$test->getFinishedAt()) {
            $enrolledUser = $this->enrolledUsers->findOneBy(['test' => $id, 'user' => $this->user->getId()]);
            if ($enrolledUser && !$enrolledUser->isLocked()) {
                $enrolledUser->setLocked();
                $this->enrolledUsers->persist($enrolledUser);
                $this->enrolledUsers->flush();
            }
        }

        $this->finalizePost($this->link('default', ['id' => $id, 'question' => null]));
    }

    /**
     * Handle the manual grading form submit.
     */
    public function handlePointsOverride(string $id): void
    {
        if ($this->user->getRole() === User::ROLE_STUDENT) {
            $this->error("The user does not have sufficient privileges to perform this operation.", 403);
        }

        $this->getTest($id, true); // true = test must be finished

        // find the question and the answer being graded
        $question = $this->getSelectedQuestion();
        $answer = $question->getLastAnswer();
        if ($answer === null) {
            $this->finalizePostError("Cannot override question with no answer.");
        }

        $enrolledUser = $this->getEnrolledUser($id);
        if (!$enrolledUser) {
            $this->finalizePostError("The enrolled user record is missing!");
        }

        // get form data
        $req = $this->getRequest();
        $pointsOverride = trim($req->getPost('pointsOverride'));
        if ($pointsOverride !== '' && !is_numeric($pointsOverride)) {
            $this->finalizePostError("Invalid points override value.");
        }

        $publicComment = trim($req->getPost('publicComment'));
        $privateComment = trim($req->getPost('privateComment'));

        $answer->setPoints($pointsOverride !== '' ? (int)$pointsOverride : null, false); // false = not the auto-points
        $answer->setPublicComment($publicComment);
        $answer->setPrivateComment($privateComment);
        $this->answers->persist($answer);

        $this->testOrchestrator->updateScore($enrolledUser);

        $this->finalizePost($this->link('default', ['id' => $id, 'question' => $this->question]));
    }

    public function renderDefault(string $id)
    {
        $this->template->test = $test = $this->getTest($id);
        $this->template->locale = $this->selectedLocale;
        $this->template->isSupervisor = $this->user->getRole() !== User::ROLE_STUDENT;

        $enrolledUser = $this->getEnrolledUser($id);
        if (!$enrolledUser) {
            $this->error("Selected user is not enrolled for the test.", 404);
        }
        $this->template->enrolledUser = $enrolledUser;

        if ($enrolledUser->getUser()->getId() !== $this->user->getId()) {
            $this->template->selectedUser = $enrolledUser->getUser();
            $this->template->readonly = true;
        } else {
            $this->template->selectedUser = null;
            $this->template->readonly = $test->getFinishedAt() !== null || $enrolledUser->isLocked();
        }

        if ($test->getStartedAt() !== null) {
            $questions = $enrolledUser->getQuestions()->toArray();
            $selectedQuestionIdx = $this->getSelectedQuestionIndex($questions, $test->getFinishedAt() !== null);

            $this->template->canSeeResults = $this->user->getRole() !== User::ROLE_STUDENT
                || ($test->getFinishedAt() !== null && $enrolledUser->hasScore());

            $this->template->questions = $questions;
            $this->template->selectedQuestionIdx = $selectedQuestionIdx;
            $this->template->previousQuestion = $selectedQuestionIdx > 0 ? $questions[$selectedQuestionIdx - 1] : null;
            $this->template->nextQuestion = $selectedQuestionIdx < count($questions) - 1
                ? $questions[$selectedQuestionIdx + 1] : null;

            if ($selectedQuestionIdx < count($questions)) {
                $this->template->selectedQuestion = $selectedQuestion = $questions[$selectedQuestionIdx];
                $this->template->selectedQuestionId = $selectedQuestion->getId();
                $questionData = $selectedQuestion->getQuestion($this->questionFactory);
                $this->template->questionText = $questionData->getText($this->selectedLocale);
                $this->template->questionItemsCount = $questionData->getItemsCount();

                // render the answer/form
                $engine = $this->latteFactory->create();
                $answer = $selectedQuestion->getLastAnswer();
                $answerData = $answer?->getAnswer();
                $this->template->answer = $answer;
                if (!$this->template->readonly) {
                    // still open -> show form
                    $this->template->questionForm
                        = $questionData->renderFormContent($engine, $this->selectedLocale, $answerData);
                } else {
                    // readonly -> show the last submitted answer (and correctness if available)
                    $this->template->answerMistakes = $answerData !== null && $this->template->canSeeResults
                        ? $questionData->evaluateAnswer($answerData) : null;
                    $this->template->questionResult
                        = $questionData->renderResultContent(
                            $engine,
                            $this->selectedLocale,
                            $answerData,
                            $this->template->canSeeResults ? $this->template->answerMistakes : null
                        );
                }

                // teacher can see a correct answer
                if ($this->user->getRole() !== User::ROLE_STUDENT && $this->template->answerMistakes !== 0) {
                    $this->template->correctAnswer = $questionData->renderCorrectContent(
                        $engine,
                        $this->selectedLocale,
                    );
                }
            }
        }
    }
}
