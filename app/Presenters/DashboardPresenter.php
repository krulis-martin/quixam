<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;

final class DashboardPresenter extends TestHandlingPresenter
{
    /*
     * Renderers
     */

    public function renderDefault()
    {
        $this->template->locale = $this->selectedLocale;
        $this->template->userData = $this->user;

        $isSupervisor = $this->user->getRole() !== User::ROLE_STUDENT;
        $this->template->isSupervisor = $isSupervisor;

        $tests = [];
        if ($isSupervisor) {
            $supervisedTests = $this->user->getRole() === User::ROLE_ADMIN
                ? $this->testTerms->findBy(['archivedAt' => null], ['scheduledAt' => 'ASC'])
                : $this->testTerms->getTermsUserSupervises($this->user);
            foreach ($supervisedTests as $test) {
                $tests[] = [$test, 'supervised', null];
            }
        }

        $this->template->hasActiveTest = false;
        foreach ($this->enrolledUsers->findBy(['user' => $this->user->getId()]) as $enrolled) {
            $tests[] = [$enrolled->getTest(), 'enrolled', $enrolled];
            if ($enrolled->getTest()->getFinishedAt() === null) {
                $this->template->hasActiveTest = true;
            }
        }
        foreach ($this->enrollmentRegistrations->findBy(['user' => $this->user->getId()]) as $registered) {
            $tests[] = [$registered->getTest(), 'registered', $registered];
        }

        usort($tests, function ($a, $b) {
            return $a[0]->getScheduledAt() <=> $b[0]->getScheduledAt();
        });

        $this->template->tests = $tests;
    }
}
