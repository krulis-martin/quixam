<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Repository\Users;
use App\Model\Entity\User;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use Psr\Log\LoggerInterface;

final class UserPresenter extends AuthenticatedPresenter
{
    /** @var Passwords @inject */
    public $passwordsService;

    /** @var LoggerInterface @inject **/
    public $logger;

    /** @var Users @inject */
    public $users;

    protected function createPasswordForm(bool $verifyOldPassword): Form
    {
        $form = new Form();
        if ($verifyOldPassword) {
            $form->addPassword('password', $this->translator->translate('locale.user.password.label'))
                ->setRequired($this->translator->translate('locale.user.password.required'));
        }

        $form->addPassword('passwordNew', $this->translator->translate('locale.user.password.labelNew'))
            ->setRequired($this->translator->translate('locale.user.password.required'))
            ->addRule(
                $form::MIN_LENGTH,
                $this->translator->translate('locale.user.password.minLength', [ 'length' => 5 ]),
                5
            );

        $form->addPassword('passwordVerify', $this->translator->translate('locale.user.password.labelVerify'))
            ->setRequired($this->translator->translate('locale.user.password.required2'))
            ->addRule($form::EQUAL, $this->translator->translate('locale.user.password.match'), $form['passwordNew'])
            ->setOmitted();

        $form->addSubmit(
            'send',
            $this->translator->translate(
                $verifyOldPassword ? 'locale.user.password.changeButton' : 'locale.user.password.setButton'
            )
        );
        $form->onSuccess[] = [$this, 'passwordFormHandler'];

        self::formForBootstrap($form);
        return $form;
    }

    protected function createComponentChangePasswordForm(): Form
    {
        return $this->createPasswordForm(true);
    }

    protected function createComponentSetPasswordForm(): Form
    {
        return $this->createPasswordForm(false);
    }

    public function passwordFormHandler(Form $form, $data): void
    {
        $user = $this->getUser()->getIdentity()->getUserData();
        if ($user->isPasswordEmpty() || $user->passwordsMatch($data['password'], $this->passwordsService)) {
            // everything checks out, lets change the password
            $user->changePassword($data['passwordNew'], $this->passwordsService);
            $this->users->persist($user);
            $this->logger->info(sprintf(
                "User %s %s (%s) changed own password.",
                $user->getFirstName(),
                $user->getLastName(),
                $user->getEmail()
            ));

            $this->flashMessage($this->translator->translate('locale.user.password.changeSuccess'), "success");
            $this->redirect('this');
        } else {
            $this->logger->warning(sprintf(
                "User %s %s (%s) attempted to change password but failed old password verification.",
                $user->getFirstName(),
                $user->getLastName(),
                $user->getEmail()
            ));
            $form['password']->addError($this->translator->translate('locale.user.password.wrongOldPassword'));
        }
    }

    public function renderDefault(): void
    {
        $this->template->user = $this->getUser()->getIdentity()->getUserData();
    }
}
