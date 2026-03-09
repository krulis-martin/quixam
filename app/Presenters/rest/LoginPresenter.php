<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\Users;
use App\Model\Repository\EnrollmentRegistrations;
use Nette\Security\Passwords;
use Nette\Security\AuthenticationException;
use Psr\Log\LoggerInterface;

final class RestLoginPresenter extends RestPresenter
{
    /** @var Users @inject */
    public $users;

    /** @var EnrollmentRegistrations @inject */
    public $registrations;

    /** @var Passwords @inject */
    public $passwords;

    /** @var LoggerInterface @inject **/
    public $logger;

    /**
     * Get authentication token for the user by verifying the provided credentials.
     * Parameters:
     * - login
     * - password
     * - expiration (optional, in seconds)
     */
    public function actionGetToken(): void
    {
        // verify the credentials
        $req = $this->getRequest();
        $login = $req->getPost('login');
        $password = $req->getPost('password');
        $expiration = (int)$req->getPost('expiration');

        $user = $this->users->findByEmail($login);
        if (!$user || !$user->passwordsMatch($password, $this->passwords)) {
            throw new AuthenticationException('Invalid user credentials.');
        }

        // update last login time stamp
        $user->updateLastAuthenticationAt();
        $this->users->persist($user);

        // create an access token for the user
        $token = $this->accessTokenManager->issueToken($user, $expiration);

        // return the token in the response
        $this->sendSuccessResponse($token);
    }
}
