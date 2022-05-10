<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;
use App\Model\Repository\TestTerms;
use Nette;

final class DashboardPresenter extends AuthenticatedPresenter
{
    /** @var TestTerms @inject */
    public $testTerms;

    public function renderDefault()
    {
        $this->template->locale = $this->selectedLocale;
        $this->template->isSupervisor = $this->user->getRole() !== User::ROLE_STUDENT;
        $this->template->enrolledTests = $this->testTerms->getTermsUserIsEnrolledFor($this->user);
        $this->template->registeredTests = $this->testTerms->getTermsUserIsRegisteredFor($this->user);
    }
}
