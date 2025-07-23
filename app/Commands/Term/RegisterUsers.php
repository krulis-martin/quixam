<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\EnrollmentRegistration;
use App\Model\Entity\TestTerm;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Repository\TestTerms;
use App\Model\Repository\Users;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;
use RuntimeException;

/**
 * A console command that creates enrollment registrations from csv file.
 */
#[AsCommand(name: 'terms:registerUsers', description: 'Create enrollment registrations from a CSV file.')]
class RegisterUsers extends BaseCommand
{
    /** @var EnrollmentRegistrations */
    private $enrollmentRegistrations;

    /** @var TestTerms */
    private $testTerms;

    /** @var Users */
    private $users;

    public function __construct(EnrollmentRegistrations $enrollmentRegistrations, TestTerms $testTerms, Users $users)
    {
        parent::__construct();
        $this->enrollmentRegistrations = $enrollmentRegistrations;
        $this->testTerms = $testTerms;
        $this->users = $users;
    }

    protected function configure()
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Path to the .csv file with user identifiers. First line must be the header.'
        );
        $this->addOption(
            'termId',
            null,
            InputOption::VALUE_OPTIONAL,
            'ID of the term for which the registrations are being loaded.'
        );
        $this->addOption('externalId', null, InputOption::VALUE_OPTIONAL, 'Name of the column with the external id.');
        $this->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Name of the column with the email.');
        $this->addOption(
            'csvSeparator',
            null,
            InputOption::VALUE_OPTIONAL,
            'Separator of CSV records (";" is the default).',
            ';'
        );
    }

    /**
     * Load csv file and return it as an array of associative arrays (keys are column names taken from the header).
     * @return array[]
     */
    protected function loadCSV(): array
    {
        $separator = $this->input->getOption('csvSeparator');
        $file = $this->input->getArgument('file');
        if (!file_exists($file) || !($fp = fopen($file, 'r'))) {
            throw new RuntimeException("Unable to open file '$file'");
        }

        if (!($header = fgetcsv($fp, 65536, $separator))) {
            throw new RuntimeException("No CSV heder found in '$file'.");
        }

        $externalId = $this->input->getOption('externalId');
        if ($externalId && !in_array($externalId, $header)) {
            throw new RuntimeException(
                "Column '$externalId' specified as eternal id, but no such column is in the file '$file'."
            );
        }

        $email = $this->input->getOption('email');
        if ($email && !in_array($email, $header)) {
            throw new RuntimeException(
                "Column '$email' specified as email, but no such column is in the file '$file'."
            );
        }

        $result = [];
        while (($line = fgetcsv($fp, 65536, $separator))) {
            if (count($line) !== count($header)) {
                throw new RuntimeException("CSV file '$file' has irregular structure.");
            }

            // use header to label cells by column names
            $row = [];
            foreach ($header as $idx => $name) {
                $row[$name] = $line[$idx];
            }

            $result[] = $row;
        }

        fclose($fp);
        return $result;
    }

    /**
     * Fetch TestTerm which is being filled with registrations.
     * @return TestTerm
     */
    protected function getTestTerm(): TestTerm
    {
        $termId = $this->input->getOption('termId');
        if ($termId) {
            return $this->testTerms->findOrThrow($termId);
        }

        $terms = $this->testTerms->findBy(
            ['startedAt' => null, 'finishedAt' => null, 'archivedAt' => null],
            ['scheduledAt' => 'ASC']
        );
        return $this->select('Select term', $terms, function (TestTerm $term): string {
            $date = $term->getScheduledAt() ? $term->getScheduledAt()->format('j.n.Y H:i') : 'not scheduled yet';
            return $term->getId() . ': ' . $term->getCaption('en') . " ($date)";
        });
    }

    /**
     * Create enrollment registration entity based on csv row.
     */
    protected function createRegistration(TestTerm $term, array $csvRow)
    {
        $externalId = $this->input->getOption('externalId');
        $externalId = $externalId ? ($csvRow[$externalId] ?? null) : null;
        $email = $this->input->getOption('email');
        $email = $email ? ($csvRow[$email] ?? null) : null;
        $user = null;

        if ($externalId) {
            $user = $this->users->findByExternalId($externalId);
        }

        if (!$user && $email) {
            $user = $this->users->findByEmail($email);
        }

        if (!$user && !$externalId && !$email) {
            return; // no data for creation
        }

        // create registration entity
        $registration = new EnrollmentRegistration($term, $user);
        if ($externalId) {
            $registration->setExternalId($externalId);
        }
        if ($email) {
            $registration->setEmail($email);
        }
        $this->enrollmentRegistrations->persist($registration);

        // notification
        if ($user) {
            $this->output->writeln("User " . $user->getId() . " got registered.");
        } elseif ($externalId) {
            $this->output->writeln("User with external ID " . $externalId . " got registered.");
        } else {
            $this->output->writeln("User with email " . $email . " got registered.");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $data = $this->loadCSV();
            $term = $this->getTestTerm();
            foreach ($data as $row) {
                $this->createRegistration($term, $row);
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }
    }
}
