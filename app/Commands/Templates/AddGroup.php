<?php

declare(strict_types=1);

namespace App\Console;

use App\Helpers\TemplatesActions;
use App\Model\Entity\TemplateTest;
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
 * A console command that creates or replaces template questions group.
 */
#[AsCommand(name: 'templates:addGroup', description: 'Add/update template questions group.')]
class AddGroupTemplate extends BaseCommand
{
    /** @var TemplatesActions */
    private $templatesActions;

    public function __construct(TemplatesActions $templatesActions)
    {
        parent::__construct();
        $this->templatesActions = $templatesActions;
    }

    protected function configure()
    {
        $this->addArgument('test', InputArgument::REQUIRED, 'External ID of the test template.');
        $this->addArgument('externalId', InputArgument::REQUIRED, 'External ID of the template questions group.');
        $this->addOption(
            'points',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of points awarded for each question in this group.'
        );
        $this->addOption(
            'count',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of questions selected from this group.'
        );
        $this->addOption(
            'ordering',
            null,
            InputOption::VALUE_OPTIONAL,
            'An index used for sorting the groups when the test is being assembled.'
        );
    }

    /**
     * Retrieves test ID from input and then loads its entity.
     * Throws an exception if the test does not exist.
     */
    protected function getTemplateTest(): TemplateTest
    {
        $testExternalId = $this->input->getArgument('test');
        $test = $this->templatesActions->getTemplateTest($testExternalId);
        if (!$test) {
            throw new RuntimeException("Test template '$testExternalId' does not exist.");
        }
        return $test;
    }

    /**
     * Retrieve integer value greater than zero from an input option.
     * @param string $key name of the option
     * @return int|null
     */
    protected function getIntOption(string $key, bool $positive = false): ?int
    {
        $value = $this->input->getOption($key);
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException("Option --$key is expected to have numeric value, but '$value' was given.");
        }

        $value = (int)$value;
        if ($positive && $value < 1) {
            throw new RuntimeException("Value of --$key is expected to be greater than 0, but '$value' was given.");
        }

        return $value;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $groupId = $input->getArgument('externalId');

        try {
            $test = $this->getTemplateTest();

            $ordering = $this->getIntOption('ordering', true);
            $count = $this->getIntOption('count', true);
            $points = $this->getIntOption('points');
            $pointsPerItem = $this->getIntOption('pointsPerItem');

            $res = $this->templatesActions->addGroup($test, $groupId, $ordering, $count, $points, $pointsPerItem);
            if ($res === null) {
                $output->writeln("Creating new '$groupId' template group ...");
            } elseif ($res === false) {
                $output->writeln(
                    "Replacing '$groupId' template group, updating links, and soft-deleting previous version ..."
                );
            } else {
                $output->writeln("Nothing changed in group '$groupId', ending without update.");
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Adding '$groupId' template group failed: $msg");
            return Command::FAILURE;
        }
    }
}
