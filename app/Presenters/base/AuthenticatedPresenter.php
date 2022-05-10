<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Controls\MenuControl;

/**
 * Base presenter for all pages that expect the user is signed in.
 */
class AuthenticatedPresenter extends BasePresenter
{
    private function isUserLoggedAndValid(): bool
    {
        if (!$this->getUser()->isLoggedIn()) {
            return false;
        }

        /** @var \App\Security\Identity */
        $identity = $this->getUser()->getIdentity();
        $user = $identity->getUserData();
        if (!$user) {
            $this->getUser()->logout();
            return false;
        }

        return true;
    }

    protected function startup()
    {
        parent::startup();
        if (!$this->isUserLoggedAndValid()) {
            $previousLink = base64_encode($this->link('this'));
            $url = $this->link('Login:default', [ 'previousLink' => $previousLink ]);
            $this->redirectUrl($url);
        }
    }
}
