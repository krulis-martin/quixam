<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Controls\MenuControl;

/**
 * Common prececessor of all presenters.
 */
class BasePresenter extends Presenter
{
    /** @var ITranslator @inject */
    public $translator;

    /** @var TranslatorSessionResolver @inject */
    public $translatorSessionResolver;

    public function createComponentMenu(): MenuControl
    {
        return new MenuControl($this->translator, $this->translatorSessionResolver);
    }
}
