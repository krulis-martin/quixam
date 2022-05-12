<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\User;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Controls\MenuControl;
use ReflectionMethod;

/**
 * Base presenter for all pages that expect the user is signed in.
 */
class AuthenticatedPresenter extends BasePresenter
{
    /** @var User|null */
    protected $user = null;

    private function getVerifiedLoggedUser(): ?User
    {
        if (!$this->getUser()->isLoggedIn()) {
            return null;
        }

        /** @var \App\Security\Identity */
        $identity = $this->getUser()->getIdentity();
        $user = $identity->getUserData();
        if (!$user) {
            $this->getUser()->logout();
            return null;
        }

        return $user;
    }

    protected function startup()
    {
        parent::startup();
        $this->user = $this->getVerifiedLoggedUser();
        if (!$this->user) {
            $previousLink = base64_encode($this->link('this'));
            $url = $this->link('Login:default', [ 'previousLink' => $previousLink ]);
            $this->redirectUrl($url);
        }

        if ($this->user->getRole() !== User::ROLE_ADMIN) {
            // users (except for admin) should check the access permissions
            // (if corresponding 'check' method exists)
            $params = $this->getRequest()->getParameters();
            $checkMethod = "check" . ucfirst($params['action'] ?? '');
            if (method_exists($this, $checkMethod)) {
                $args = [];
                $refMethod = new ReflectionMethod($this, $checkMethod);
                foreach ($refMethod->getParameters() as $p) {
                    $name = $p->getName();
                    $args[] = $params[$name] ?? null;
                }

                if (!$this->$checkMethod(...$args)) {
                    $this->error("The user does not have sufficient priviledges to access selected content.", 403);
                }
            }
        }
    }
}
