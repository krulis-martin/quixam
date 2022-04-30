<?php

declare(strict_types=1);

namespace App\Controls;

use Nette\Application\UI\Control;

class MenuControl extends Control
{
    public function render(): void
    {
        $this->template->userLoggedIn = false; // TODO
        $this->template->render(__DIR__ . '/templates/menu.latte');
    }
}
