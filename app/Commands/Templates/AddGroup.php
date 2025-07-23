<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
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
    /** @var TemplateTests */
    private $templateTests;

    /** @var TemplateQuestions */
    private $templateQuestions;

    /** @var TemplateQuestionsGroups */
    private $templateQuestionsGroups;

    public function __construct(
        TemplateTests $templateTests,
        TemplateQuestions $templateQuestions,
        TemplateQuestionsGroups $templateQuestionsGroups
    ) {
        parent::__construct();
        $this->templateTests = $templateTests;
        $this->templateQuestions = $templateQuestions;
        $this->templateQuestionsGroups = $templateQuestionsGroups;
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
        $test = $this->templateTests->findOneBy(['externalId' => $testExternalId]);
        if (!$test) {
            throw new RuntimeException("Test template '$testExternalId' does not exist.");
        }
        return $test;
    }

    /**
     * Retrieve integer value greater than zero from an input option.
     * @param string $key name of the option
     * @param int|null $default used when the options is missing.
     * @return int|null
     */
    protected function getIntOption(string $key, ?int $default = null): ?int
    {
        $value = $this->input->getOption($key);
        if ($value === null) {
            return $default;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException("Option --$key is expected to have numeric value, but '$value' was given.");
        }

        $value = (int)$value;
        if ($value < 1) {
            throw new RuntimeException("Value of --$key is expected to be greater than 0, but '$value' was given.");
        }

        return $value;
    }

    /**
     * Get a default for ordering parameter by scanning for existing groups and returning +1.
     * @param TemplateTest $test groups of which are scanned
     * @return int new ordering value for the created entity
     */
    protected function getMaxOrdering(TemplateTest $test): int
    {
        $groups = $test->getQuestionGroups();
        if ($groups->isEmpty()) {
            return 1;
        }
        return $groups->last()->getOrdering() + 1;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $test = $this->getTemplateTest();

            $groupId = $input->getArgument('externalId');
            $group = $this->templateQuestionsGroups->findOneBy(['externalId' => $groupId, 'test' => $test->getId()]);

            $ordering = $this->getIntOption('ordering', $group ? $group->getOrdering() : $this->getMaxOrdering($test));
            $count = $this->getIntOption('count', $group ? $group->getSelectCount() : 1);
            $points = $this->getIntOption('points', $group ? $group->getPoints() : 1);

            if (
                $group
                && $group->getOrdering() === $ordering
                && $group->getSelectCount() === $count
                && $group->getPoints() === $points
            ) {
                $output->writeln("Nothing changed in group '$groupId', ending without update.");
                return Command::SUCCESS;
            }

            $output->writeln("Creating '$groupId' template group ...");
            $newGroup = new TemplateQuestionsGroup($test, $ordering, $count, $points, $groupId, $group);
            $this->templateQuestionsGroups->persist($newGroup);

            if (!empty($group)) {
                $output->writeln("Updating links and soft-deleting previous version ...");
                $this->templateQuestions->reconnectQuestions($group, $newGroup);
                $this->templateQuestionsGroups->remove($group);
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
