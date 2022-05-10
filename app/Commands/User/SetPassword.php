<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use DateTime;
use DateInterval;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Nette\Security\Passwords;

/**
 * A console command that changes password of given user (with no checking).
 */
class SetPassword extends BaseCommand
{
    protected static $defaultName = 'users:passwd';

    /** @var Users */
    private $users;

    /** @var Passwords */
    private $passwordsService;

    public function __construct(Users $users, Passwords $passwordsService)
    {
        parent::__construct();
        $this->users = $users;
        $this->passwordsService = $passwordsService;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription('Set password of given user.');
        $this->addArgument(
            'email',
            InputArgument::REQUIRED,
            'Email (login) of the user who needs the password (re)set.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        // get the user ...
        $email = $input->getArgument('email');
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $output->writeln("No user $email exists.");
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            "Changing password for user %s %s (%s).",
            $user->getFirstName(),
            $user->getLastName(),
            $user->getId()
        ));

        // get the password from the input and verify it
        $password = $this->prompt("New password: ", '', true); // true = hide input
        $passwordCheck = $this->prompt("Verify password: ", '', true);
        if ($password !== $passwordCheck) {
            $output->writeln("Passwords do not match.");
            return Command::FAILURE;
        }

        if (strlen($password) < 5) {
            $output->writeln("Passwords is too short, at least 5 characters must be given.");
            return Command::FAILURE;
        }

        // change the password and write to the database
        $user->changePassword($password, $this->passwordsService);
        $this->users->persist($user);
        $output->writeln("Passwords was successfully modified.");
        return Command::SUCCESS;
    }
}
