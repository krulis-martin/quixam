<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Repository\Questions;
use App\Model\Repository\TestTerms;
use App\Model\Entity\EnrolledUser;
use App\Model\Entity\EnrollmentRegistration;
use App\Model\Entity\Question;
use App\Model\Entity\User;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette;
use DateTime;

/**
 * Displays users enrolled for particular test.
 */
final class EnrolledPresenter extends AuthenticatedPresenter
{
    /** @var EnrolledUsers @inject */
    public $enrolledUsers;

    /** @var EnrollmentRegistrations @inject */
    public $enrollmentRegistrations;

    /** @var Questions @inject */
    public $questions;

    /** @var TestTerms @inject */
    public $testTerms;

    /** @var LatteFactory @inject */
    public $latteFactory;

    /**
     * Signal handler for AJAX call that locks or unlocks given enrolled user.
     * @param string $id of the enrolled user
     * @param bool $state new locked state
     */
    public function handleLock(string $id, bool $state): void
    {
        $enrolledUser = $this->enrolledUsers->get($id);
        if (!$enrolledUser) {
            $this->finalizePostError("Enrolled user record no longer exists.");
        }
        $testId = $enrolledUser->getTest()->getId();

        $enrolledUser->setLocked($state);
        $this->enrolledUsers->persist($enrolledUser);
        $this->enrolledUsers->flush();

        $this->finalizePost($this->link('default', [ 'id' => $testId ]));
    }

    /**
     * Signal handler for AJAX call that removes enrolled user and the entire instance of the test.
     * @param string $id of the enrolled user
     */
    public function handleDeleteEnrolled(string $id): void
    {
        $enrolledUser = $this->enrolledUsers->get($id);
        if (!$enrolledUser) {
            $this->finalizePostError("Enrolled user record no longer exists.");
        }
        $testId = $enrolledUser->getTest()->getId();

        $registration = $this->enrollmentRegistrations->findOneBy([
            'test' => $testId, 'user' => $enrolledUser->getUser()->getId()
        ]);
        if ($registration === null) {
            $registration = new EnrollmentRegistration($enrolledUser->getTest(), $enrolledUser->getUser());
            $this->enrollmentRegistrations->persist($registration);
        }

        foreach ($enrolledUser->getQuestions() as $question) {
            $question->setLastAnswer(null);
            $this->questions->persist($question);
        }
        $this->enrolledUsers->remove($enrolledUser);
        $this->enrolledUsers->flush();

        $this->finalizePost($this->link('default', [ 'id' => $testId ]));
    }

    /**
     * Signal handler for AJAX call that removes enrollment registration of a particular user.
     * @param string $id of the registration
     */
    public function handleDeleteRegistration(string $id): void
    {
        $registration = $this->enrollmentRegistrations->get($id);
        if (!$registration) {
            $this->finalizePostError("Registration no longer exists.");
        }
        $testId = $registration->getTest()->getId();

        $this->enrollmentRegistrations->remove($registration);
        $this->enrollmentRegistrations->flush();

        $this->finalizePost($this->link('default', [ 'id' => $testId ]));
    }

    /**
     * Automatically invoked permissions check for the Default action.
     * @return bool true if the user has access to display default view
     */
    public function checkDefault(string $id): bool
    {
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

        $enrolledUsers = $test->getEnrolledUsers()->toArray();
        usort($enrolledUsers, function ($a, $b) {
            $a = $a->getUser();
            $b = $b->getUser();
            $res = strcmp($a->getLastName(), $b->getLastName());
            return $res !== 0 ? $res : strcmp($a->getFirstName(), $b->getFirstName());
        });
        $this->template->enrolledUsers = $enrolledUsers;

        $this->template->questions = $this->questions->getQuestionsOfTestSorted($test);

        $this->template->onlineDateThreshold = new DateTime("15 minutes ago");
        //$this->template->onlineDateThreshold->sub("15 minutes");
    }
}
