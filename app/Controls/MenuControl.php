<?php

declare(strict_types=1);

namespace App\Controls;

use App\Model\Entity\User;
use App\Security\IExternalAuthenticator;
use Nette\Application\UI\Control;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;

class MenuControl extends Control
{
    /** @var ITranslator */
    public $translator;

    /** @var TranslatorSessionResolver */
    public $translatorSessionResolver;

    public function __construct(ITranslator $translator, TranslatorSessionResolver $translatorSessionResolver)
    {
        $this->translator = $translator;
        $this->translatorSessionResolver = $translatorSessionResolver;
    }

    public function handleChangeLocale(string $locale): void
    {
        $this->translatorSessionResolver->setLocale($locale);
        $this->redirect('this');
    }

    public function handleLogout(): void
    {
        /** @var \App\Presenters\BasePresenter */
        $presenter = $this->getPresenter();
        $presenter->getUser()->logout();

        $presenter->externalAuthenticator->initialize();
        $presenter->externalAuthenticator->logout($presenter->link('//Login:default'));

        // if the previous logout did not end in redirect, make it ourselves
        $presenter->finalizePost($presenter->link('Login:default'));
    }

    public function render(): void
    {
        if ($this->getPresenter()->getUser()->isLoggedIn()) {
            /** @var \App\Security\Identity */
            $identity =  $this->getPresenter()->getUser()->getIdentity();
            $this->template->user = $identity->getUserData();
        } else {
            $this->template->user = null;
        }

        /** @phpstan-ignore-next-line */
        $this->template->selectedLocale = $this->translator->getLocale();
        $this->template->setFile(__DIR__ . '/templates/menu.latte');
        $this->template->render();
    }
}
