<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use App\Controls\MenuControl;

/**
 * Common prececessor of all presenters.
 */
class BasePresenter extends Presenter
{
    public function handleChangeLocale(string $locale): void
    {
        $this->translatorSessionResolver->setLocale($locale);
        $this->redirect('this');
    }

    public function createComponentMenu(): MenuControl
    {
        return new MenuControl();
    }
}
