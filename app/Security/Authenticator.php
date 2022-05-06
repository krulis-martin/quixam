<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\Repository\Users;
use Nette;
use Nette\Security\Authenticator as SecurityAuthenticator;
use Nette\Security\IdentityHandler;
use Nette\Security\IIdentity;
use Nette\Security\Passwords;
use Nette\Security\AuthenticationException;
use Nette\Security\SimpleIdentity;

class Authenticator implements SecurityAuthenticator, IdentityHandler
{
    /** @var Users */
    private $users;

    /** @var Passwords */
    private $passwords;

    public function __construct(Users $users, Passwords $passwords)
    {
        $this->users = $users;
        $this->passwords = $passwords;
    }

    public function authenticate(string $username, string $password): Identity
    {
        $user = $this->users->findByEmail($username);

        if (!$user || !$user->passwordsMatch($password, $this->passwords)) {
            throw new AuthenticationException('Invalid user credentials.');
        }

        // update last login time stamp
        $user->updateLastAuthenticationAt();
        $this->users->persist($user);

        return new Identity($user);
    }

    public function sleepIdentity(IIdentity $identity): IIdentity
    {
        // we need to convert the identity to make it serializable
        return new SimpleIdentity($identity->getId());
    }

    public function wakeupIdentity(IIdentity $identity): ?IIdentity
    {
        // restore the identity from serialized ID
        $userId = $identity->getId();
        return new Identity($this->users->get($userId));
    }
}
