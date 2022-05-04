<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Controls\MenuControl;
use App\Exceptions\BadRequestException;

/**
 * Common predecessor of all presenters.
 */
class BasePresenter extends Presenter
{
    /** @var ITranslator @inject */
    public $translator;

    /** @var TranslatorSessionResolver @inject */
    public $translatorSessionResolver;

    /*
     * Component factories
     */

    public function createComponentMenu(): MenuControl
    {
        return new MenuControl($this->translator, $this->translatorSessionResolver);
    }

    /**
     * Complete a POST request with JSON response (if called as AJAX) or redirect (regular HTTP request).
     * @param string|null $redirect URL to which the page is redirected
     *                              (null means no redirect for AJAX and redirect to 'this' for regular HTTP)
     */
    public function finalizePost(string $redirect = null): void
    {
        if ($this->isAjax()) {
            // AJAX is handled with JSON response (redirect is suggested inside to JS handler)
            $res = [ 'ok' => true ];
            if ($redirect) {
                $res['redirect'] = $redirect;
            }
            $this->sendJson($res);
        } else {
            if (!$redirect) {
                $redirect = $this->link('this');
            }
            $this->redirectUrl($redirect);
        }
    }

    public function sendJsonError(string $msg, string $redirect = null): void
    {
        $res = [ 'ok' => false, 'error' => $msg ];
        if ($redirect) {
            $res['redirect'] = $redirect;
        }
        $this->sendJson($res);
    }
}
