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
use Nette;

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

    /** @persistent */
    public $question = null;

    private function getEnrolledUser($id): ?EnrolledUser
    {
        $enrolledUsers = $this->enrolledUsers->findBy([ 'test' => $id, 'user' => $this->user->getId() ]);
        return $enrolledUsers ? reset($enrolledUsers) : null;
    }

    private function getSelectedQuestion(EnrolledUser $enrolledUser): ?Question
    {
        if ($this->question) {
            // explicit selection by a parameter
            $question = $this->questions->get($this->question);
        }

        if (empty($question)) {
            // try to find the best default
            $question = $enrolledUser->getQuestions()->first();

            // try to find first question without answers
            foreach ($enrolledUser->getQuestions()->toArray() as $q) {
                if ($q->getLastAnswer() === null) {
                    $question = $q;
                    break;
                }
            }
        }

        return $question;
    }

    public function checkDefault(string $id): bool
    {
        if ($this->user->getRole() === User::ROLE_STUDENT) {
            // a student must be enrolled to see the test
            return $this->getEnrolledUser($id) !== null;
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
        $test = $this->testTerms->get($id);
        if (!$test) {
            $this->error("Test $id does not exist.", 404);
        }
        $enrolledUser = $this->getEnrolledUser($id);
        $selectedQuestion = $this->getSelectedQuestion($enrolledUser);

        $this->template->locale = $this->selectedLocale;
        $this->template->test = $test;
        $this->template->enrolledUser = $enrolledUser;
        $this->template->selectedQuestion = $selectedQuestion;
        if ($selectedQuestion) {
            $this->template->selectedQuestionId = $selectedQuestion ? $selectedQuestion->getId() : null;
            //$this->template->showPrevious = $enrolledUser->getQuestions()->first()->getId() !== $selecteQuestion->getId();
            //$this->template->showNext = $enrolledUser->getQuestions()->last()->getId() !== $selecteQuestion->getId();
        }
    }
}
