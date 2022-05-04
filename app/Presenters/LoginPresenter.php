<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\Users;
use Nette;
use Nette\Security\Passwords;
use Nette\Security\AuthenticationException;

final class LoginPresenter extends BasePresenter
{
    /** @var Users @inject */
    public $users;

    /** @var Passwords @inject */
    public $passwordsService;

    /** @persistent */
    public $previousLink = null;

    public function handleLogin(): void
    {
        // verify the credentials
        $req = $this->getRequest();
        $login = $req->getPost('login');
        $password = $req->getPost('password');

        try {
            $this->getUser()->login($login, $password);
        } catch (AuthenticationException $e) {
            $this->sendJsonError($this->translator->translate('login.error.invalidCredentials'));
        }

        $this->finalizePost(
            $this->previousLink ? base64_decode($this->previousLink) : $this->link('Dashboard:default')
        );
    }
}
