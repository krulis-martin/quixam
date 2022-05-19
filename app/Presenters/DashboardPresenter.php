<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\EnrolledUser;
use App\Model\Entity\EnrollmentRegistration;
use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Model\Repository\EnrolledUsers;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Repository\TestTerms;
use App\Helpers\TestOrchestrator;
use Nette;
use Tracy\ILogger;
use Exception;

final class DashboardPresenter extends AuthenticatedPresenter
{
    /** @var EnrolledUsers @inject */
    public $enrolledUsers;

    /** @var EnrollmentRegistrations @inject */
    public $enrollmentRegistrations;

    /** @var TestTerms @inject */
    public $testTerms;

    /** @var TestOrchestrator @inject */
    public $testOrchestrator;

    /** @var ILogger @inject */
    public $logger;

    /**
     * Helper function that retrieves test term by its ID.
     * The invocation is ended by finalize method in case of error.
     * @param string $id of the thes term
     * @return TestTerm|null on success (null is here only for PHPStan, it will never happen)
     */
    private function getTest(string $id): ?TestTerm
    {
        $test = $this->testTerms->get($id);
        if (!$test || $test->getArchivedAt()) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.invalidId'));
        }
        return $test;
    }

    /**
     * Helper function that retrieves test term by its ID and verifies the user can modify it.
     * The invocation is ended by finalize method in case of error.
     * @param string $id of the thes term
     * @return TestTerm|null on success (null is here only for PHPStan, it will never happen)
     */
    private function getTestForModification(string $id): ?TestTerm
    {
        $test = $this->getTest($id);
        if (
            $this->user->getRole() === User::ROLE_ADMIN ||
            ($this->user->getRole() !== User::ROLE_STUDENT && $test->getSupervisors()->contains($this->user))
        ) {
            return $test;
        }

        $this->finalizePostError($this->translator->translate('locale.dashboard.error.notAuthorized'));
        return null;
    }

    /*
     * Signals
     */

    public function handleStart(string $id)
    {
        $test = $this->getTestForModification($id);
        if ($test->getStartedAt()) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.invalidTestState'));
        }
        $test->startNow();
        $this->testTerms->persist($test);
        $this->finalizePost($this->link('this'));
    }

    public function handleFinish(string $id)
    {
        $test = $this->getTestForModification($id);
        if (!$test->getStartedAt() || $test->getFinishedAt()) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.invalidTestState'));
        }

        try {
            $this->testTerms->beginTransaction();
            $this->testOrchestrator->evlauate($test);
            $test->finishNow();
            $this->testTerms->persist($test);
            $this->testTerms->commit();
        } catch (Exception $e) {
            $this->testTerms->rollback();
            $this->logger->log($e, ILogger::EXCEPTION);
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.evaluationFailure'));
        }

        $this->finalizePost($this->link('this'));
    }

    public function handleRevokeStart(string $id)
    {
        $test = $this->getTestForModification($id);
        if (!$test->getStartedAt() || $test->getFinishedAt()) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.invalidTestState'));
        }
        $test->setStartedAt(null);
        $this->testTerms->persist($test);
        $this->finalizePost($this->link('this'));
    }

    public function handleRevokeFinish(string $id)
    {
        $test = $this->getTestForModification($id);
        if (!$test->getFinishedAt()) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.invalidTestState'));
        }
        $test->setFinishedAt(null);
        $this->testTerms->persist($test);
        $this->finalizePost($this->link('this'));
    }

    public function handleEnroll(string $id)
    {
        $test = $this->getTest($id);

        // if the user is already enrolled -> just redirect
        if ($this->enrolledUsers->findOneBy([ 'test' => $id, 'user' => $this->user->getId() ]) !== null) {
            $this->finalizePost($this->link('Test:default', [ 'id' => $id ]));
        }

        // check the registration
        $this->enrollmentRegistrations->reassociateUser($this->user);
        $registration = $this->enrollmentRegistrations->findOneBy([ 'test' => $id, 'user' => $this->user->getId() ]);
        if (!$registration) {
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.notRegistered'));
        }

        try {
            $this->enrolledUsers->beginTransaction();

            // create enrollment and delete registration
            $enrolledUser = new EnrolledUser($test, $this->user);
            $this->enrollmentRegistrations->remove($registration);
            $this->enrolledUsers->persist($enrolledUser);

            $this->testOrchestrator->instantiate($enrolledUser);
            $this->enrolledUsers->commit();
        } catch (Exception $e) {
            $this->enrolledUsers->rollback();
            $this->logger->log($e, ILogger::EXCEPTION);
            $this->finalizePostError($this->translator->translate('locale.dashboard.error.instantiationFailure'));
        }

        $this->finalizePost($this->link('Test:default', [ 'id' => $id ]));
    }

    /*
     * Renderers
     */

    public function renderDefault()
    {
        $this->template->locale = $this->selectedLocale;
        $this->template->user = $this->user;

        $isSupervisor = $this->user->getRole() !== User::ROLE_STUDENT;
        $this->template->isSupervisor = $isSupervisor;

        $tests = [];
        if ($isSupervisor) {
            $supervisedTests = $this->user->getRole() === User::ROLE_ADMIN
                ? $this->testTerms->findBy([ 'archivedAt' => null ])
                : $this->testTerms->getTermsUserSupervises($this->user);
            foreach ($supervisedTests as $test) {
                $tests[] = [ $test, 'supervised' ];
            }
        }
        foreach ($this->testTerms->getTermsUserIsEnrolledFor($this->user) as $test) {
            $tests[] = [ $test, 'enrolled' ];
        }
        foreach ($this->testTerms->getTermsUserIsRegisteredFor($this->user) as $test) {
            $tests[] = [ $test, 'registered' ];
        }
        $this->template->tests = $tests;
    }
}
