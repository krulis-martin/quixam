<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\Users;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Entity\User;
use App\Security\IExternalAuthenticator;
use App\Security\Identity;
use Nette;
use Nette\Security\Passwords;
use Nette\Security\AuthenticationException;
use Psr\Log\LoggerInterface;
use CAS_Exception;

final class LoginPresenter extends BasePresenter
{
    /** @var Users @inject */
    public $users;

    /** @var EnrollmentRegistrations @inject */
    public $registrations;

    /** @var Passwords @inject */
    public $passwordsService;

    /** @var LoggerInterface @inject **/
    public $logger;

    /** @persistent */
    public $previousLink = null;

    private function getRedirectUrl(): string
    {
        return $this->previousLink ? base64_decode($this->previousLink) : $this->link('Dashboard:default');
    }

    protected function startup()
    {
        parent::startup();

        if ($this->getUser()->isLoggedIn() && !$this->isAjax()) {
            // if the user is already logged in, redirect
            $this->redirectUrl($this->getRedirectUrl());
        }
    }

    /**
     * Login by local credentials signal handler.
     */
    public function handleLogin(): void
    {
        // verify the credentials
        $req = $this->getRequest();
        $login = $req->getPost('login');
        $password = $req->getPost('password');

        try {
            $this->getUser()->login($login, $password);
        } catch (AuthenticationException $e) {
            $this->finalizePostError($this->translator->translate('locale.login.error.invalidCredentials'));
        }

        $this->finalizePost($this->getRedirectUrl());
    }

    /**
     * Helper function that takes the data from external authenticator and finds corresponding local user.
     * If the user does not exist, it attempts matching via emails and optionally creates the new entity.
     * @return User|null the user entity or null if no such user exists/can be created
     */
    private function findMatchingUser($createIfNotExist = true): ?User
    {
        $eid = $this->externalAuthenticator->getUserId();
        if (!$eid) {
            $this->flashMessage($this->translator->translate('locale.login.error.invalidCredentials'), "danger");
            return null;
        }

        // exact match of external ID
        $user = $this->users->findByExternalId($eid);
        if ($user) {
            return $user;
        }

        // the internal account can be paired with external ID by email
        $emails = $this->externalAuthenticator->getUserEmails();
        foreach ($emails as $email) {
            $user = $this->users->findByEmail($email);
            if (!$user) {
                continue;
            }

            if (!$user->getExternalId()) {
                $user->setExternalId($eid);
                $this->users->persist($user);
                return $user;
            } else {
                $this->flashMessage($this->translator->translate('locale.login.error.externalCollision'), "danger");
                $this->logger->warning("External authentication detected collision. "
                    . "Email '$email' is already in use but also colliding with user '$eid'.");
                return null;
            }
        }

        // create brand new user entity
        $role = $this->externalAuthenticator->getUserSuggestedRole();
        $firstName = $this->externalAuthenticator->getUserFirstName();
        $lastName = $this->externalAuthenticator->getUserLastName();
        if ($createIfNotExist && $emails && $firstName && $lastName && $role) {
            $user = new User(reset($emails), $firstName, $lastName, $role);
            $user->setExternalId($eid);
            $user->setVerified();
            $this->users->persist($user);
            return $user;
        }

        $this->flashMessage($this->translator->translate('locale.login.error.externalRegisterFails'), "danger");
        return null;
    }

    /**
     * Login via external authenticator.
     */
    public function actionExternal(): void
    {
        try {
            $this->externalAuthenticator->initialize();

            $user = $this->externalAuthenticator->authenticate() ? $this->findMatchingUser() : null;
            if ($user) {
                $user->updateLastAuthenticationAt();
                $this->users->persist($user);

                $this->registrations->reassociateUser($user);

                $this->getUser()->login(new Identity($user));
                $this->redirectUrl($this->getRedirectUrl());
            } else {
                $this->redirect('Login:default');
            }
        } catch (CAS_Exception $e) {
            $this->logger->error("Unhandled exception: " . $e->getMessage(), [ 'exception' => $e ]);
            $this->flashMessage($this->translator->translate('locale.login.error.externalAuthFailed'), "danger");
            $this->redirect('Login:default');
        }
    }

    public function renderExternal(): void
    {
    }
}
