<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Controls\MenuControl;

/**
 * Base presenter for all pages that expect the user is signed in.
 */
class AuthenticatedPresenter extends BasePresenter
{
    /** @var User|null */
    protected $user = null;

    private function getVerifiedLoggedUser(): ?User
    {
        if (!$this->getUser()->isLoggedIn()) {
            return null;
        }

        /** @var \App\Security\Identity */
        $identity = $this->getUser()->getIdentity();
        $user = $identity->getUserData();
        if (!$user) {
            $this->getUser()->logout();
            return null;
        }

        return $user;
    }

    protected function startup()
    {
        parent::startup();
        $this->user = $this->getVerifiedLoggedUser();
        if (!$this->user) {
            $previousLink = base64_encode($this->link('this'));
            $url = $this->link('Login:default', [ 'previousLink' => $previousLink ]);
            $this->redirectUrl($url);
        }
    }
}
