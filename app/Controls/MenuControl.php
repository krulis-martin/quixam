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
        $this->getPresenter()->getUser()->logout();
        $this->getPresenter()->finalizePost($this->link('this'));
    }

    public function render(): void
    {
        $loggedIn = $this->getPresenter()->getUser()->isLoggedIn();
        $this->template->userLoggedIn = $loggedIn;
        if ($loggedIn) {
            $this->template->user = $this->getPresenter()->getUser()->getIdentity()->getUserData();
        }
        $this->template->selectedLocale = $this->translator->getLocale();
        $this->template->render(__DIR__ . '/templates/menu.latte');
    }
}
