<?php

declare(strict_types=1);

namespace App\Controls;

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
        $presenter->finalizePost($this->link('this'));
    }

    public function render(): void
    {
        $loggedIn = $this->getPresenter()->getUser()->isLoggedIn();
        $this->template->userLoggedIn = $loggedIn;
        if ($loggedIn) {
            /** @var \App\Security\Identity */
            $identity =  $this->getPresenter()->getUser()->getIdentity();
            $this->template->user = $identity->getUserData();
        }
        /** @phpstan-ignore-next-line */
        $this->template->selectedLocale = $this->translator->getLocale();
        $this->template->setFile(__DIR__ . '/templates/menu.latte');
        $this->template->render();
    }
}
