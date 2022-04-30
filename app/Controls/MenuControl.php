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

    public function render(): void
    {
        $this->template->userLoggedIn = false; // TODO
        $this->template->selectedLocale = $this->translator->getLocale();
        $this->template->render(__DIR__ . '/templates/menu.latte');
    }
}
