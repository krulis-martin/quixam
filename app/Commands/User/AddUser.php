<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Model\Repository\EnrollmentRegistrations;
use DateTime;
use DateInterval;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * A console command that creates a new user (without password).
 */
class AddUser extends BaseCommand
{
    protected static $defaultName = 'users:add';

    /** @var Users */
    private $users;

    /** @var EnrollmentRegistrations */
    private $registrations;

    public function __construct(Users $users, EnrollmentRegistrations $registrations)
    {
        parent::__construct();
        $this->users = $users;
        $this->registrations = $registrations;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription('Add new user.');
        $this->addArgument('email', InputArgument::REQUIRED, 'Email (which is also used as local login name)');
        $this->addOption('firstName', null, InputOption::VALUE_OPTIONAL, 'First name of the user.');
        $this->addOption('lastName', null, InputOption::VALUE_OPTIONAL, 'Last name of the user.');
        $this->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Role of the user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            // make sure we have all the inputs
            $email = $input->getArgument('email');
            $firstName = $lastName = $role = null; // just to satisfy phpstan
            foreach ([ 'firstName', 'lastName', 'role' ] as $key) {
                $$key = trim($input->getOption($key));
                if (empty($$key)) {
                    $$key = trim($this->prompt("$key: "));
                }

                if (empty($$key)) {
                    $output->writeln("Parameter '$key' must be set.");
                    return Command::FAILURE;
                }
            }

            if (!in_array($role, User::ROLES)) {
                $output->writeln("Unknown role '$role'.");
                return Command::FAILURE;
            }

            // Create the user
            $user = new User($email, $firstName, $lastName, $role);
            $user->setVerified();
            $this->users->persist($user);

            $this->registrations->reassociateUser($user);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }
    }
}
