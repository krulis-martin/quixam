<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\TestTerms;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\Questions;
use App\Model\Repository\Answers;
use App\Model\Entity\EnrolledUser;
use App\Model\Entity\Question;
use App\Model\Entity\User;
use App\Helpers\QuestionFactory;
use Nette;
use Nette\Bridges\ApplicationLatte\LatteFactory;

final class TestPresenter extends AuthenticatedPresenter
{
    /** @var TestTerms @inject */
    public $testTerms;

    /** @var EnrolledUsers @inject */
    public $enrolledUsers;

    /** @var Questions @inject */
    public $questions;

    /** @var Answers @inject */
    public $answers;

    /** @var QuestionFactory @inject */
    public $questionFactory;

    /** @var LatteFactory @inject */
    public $latteFactory;

    /** @persistent */
    public $question = null;

    private function getEnrolledUser($id): ?EnrolledUser
    {
        $enrolledUsers = $this->enrolledUsers->findBy([ 'test' => $id, 'user' => $this->user->getId() ]);
        return $enrolledUsers ? reset($enrolledUsers) : null;
    }

    private function getSelectedQuestion(array $questions): int
    {
        $res = count($questions);
        foreach ($questions as $idx => $question) {
            if ($this->question && $this->question === $question->getId()) {
                return $idx; // explicit selection by a parameter
            }

            if ($idx < $res && $question->getLastAnswer() === null) {
                $res = $idx;
            }
        }

        return $res;
    }

    public function checkDefault(string $id): bool
    {
        if ($this->user->getRole() === User::ROLE_STUDENT) {
            // a student must be enrolled to see the test, which has already started
            $enrolled = $this->getEnrolledUser($id);
            return $enrolled !== null && $enrolled->getTest()->getStartedAt() !== null;
        }

        if ($this->user->getRole() === User::ROLE_TEACHER) {
            // teacher must be a supervisor
            $test = $this->testTerms->get($id);
            return $test && $test->getSupervisors()->contains($this->user);
        }

        return false;
    }

    public function renderDefault(string $id)
    {
        $this->template->locale = $this->selectedLocale;

        $test = $this->testTerms->get($id);
        if (!$test) {
            $this->error("Test $id does not exist.", 404);
        }
        $this->template->test = $test;

        $enrolledUser = $this->getEnrolledUser($id); // TODO in the future allow user selection for supervisors
        if (!$enrolledUser) {
            $this->error("Selected user is not enrolled for the test.", 404);
        }
        $this->template->enrolledUser = $enrolledUser;

        if ($test->getStartedAt() !== null) {
            $questions = $enrolledUser->getQuestions()->toArray();
            $selectedQuestionIdx = $this->getSelectedQuestion($questions);

            $this->template->questions = $questions;
            $this->template->selectedQuestionIdx = $selectedQuestionIdx;
            if ($selectedQuestionIdx < count($questions)) {
                $this->template->selectedQuestion = $selectedQuestion = $questions[$selectedQuestionIdx];
                $this->template->selectedQuestionId = $selectedQuestion->getId();
                $questionData = $selectedQuestion->getQuestion($this->questionFactory);
                $this->template->questionText = $questionData->getText($this->selectedLocale);

                $engine = $this->latteFactory->create();
                $answer = $selectedQuestion->getLastAnswer() ? $selectedQuestion->getLastAnswer()->getAnswer() : null;
                if ($test->getFinishedAt() === null) {
                    // still open -> show form
                    $this->template->questionForm
                        = $questionData->renderFormContent($engine, $this->selectedLocale, $answer);
                } else {
                    // finished -> show the results
                    $this->template->questionResult
                        = $questionData->renderResultContent($engine, $this->selectedLocale, $answer);
                }
            }
        }
    }
}
