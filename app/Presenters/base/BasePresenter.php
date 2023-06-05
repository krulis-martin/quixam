<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Controls\MenuControl;
use App\Exceptions\BadRequestException;
use App\Security\IExternalAuthenticator;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\Checkbox;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;

/**
 * Common predecessor of all presenters.
 */
class BasePresenter extends Presenter
{
    /** @var ITranslator @inject */
    public $translator;

    /** @var TranslatorSessionResolver @inject */
    public $translatorSessionResolver;

    /** @var IExternalAuthenticator @inject */
    public $externalAuthenticator;

    /** @var string */
    protected $selectedLocale = 'en';

    protected function startup()
    {
        parent::startup();

        /** @phpstan-ignore-next-line */
        $this->selectedLocale = $this->translator->getLocale();
    }
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

    /**
     * Complete a POST request by sending JSON response with error (AJAX)
     * or setting a flas message and redirect to 'this' (regular requests).
     * @param string $msg error message
     */
    public function finalizePostError(string $msg): void
    {
        if ($this->isAjax()) {
            $res = [ 'ok' => false, 'error' => $msg ];
            $this->sendJson($res);
        } else {
            $this->flashMessage($msg, "danger");
            $this->redirect('this');
        }
    }

    public static function formForBootstrap(Form $form): void
    {
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null; /** @phpstan-ignore-line */
        $renderer->wrappers['pair']['container'] = 'div class="form-group row"';
        $renderer->wrappers['pair']['.error'] = 'has-danger';
        $renderer->wrappers['control']['container'] = 'div class="col-sm-9 pb-2"';
        $renderer->wrappers['label']['container'] = 'div class="col-sm-3 col-form-label"';
        $renderer->wrappers['control']['description'] = 'span class=form-text';
        $renderer->wrappers['control']['errorcontainer'] = 'span class=form-control-feedback';
        $renderer->wrappers['control']['.error'] = 'is-invalid';

        foreach ($form->getControls() as $control) {
            $type = $control->getOption('type');
            if ($type === 'button') {
                $control->getControlPrototype()->addClass(
                    empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary'
                );
                $usedPrimary = true;
            } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
                $control->getControlPrototype()->addClass('form-control');
            } elseif ($type === 'file') {
                $control->getControlPrototype()->addClass('form-control-file');
            } elseif (in_array($type, ['checkbox', 'radio'], true)) {
                if ($control instanceof Checkbox) {
                    $control->getLabelPrototype()->addClass('form-check-label');
                } else {
                    $control->getItemLabelPrototype()->addClass('form-check-label');
                }
                $control->getControlPrototype()->addClass('form-check-input');
                $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
            }
        }
    }
}
