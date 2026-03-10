<?php

declare(strict_types=1);

namespace App\Console;

use App\Helpers\TemplatesActions;
use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestionsGroup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;
use RuntimeException;

/**
 * A console command that soft-deletes given template question.
 */
#[AsCommand(name: 'templates:deleteQuestion', description: 'Soft-delete template question.')]
class DeleteQuestionTemplate extends BaseCommand
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
        $this->addArgument('group', InputArgument::REQUIRED, 'External ID of the template questions group.');
        $this->addArgument('externalId', InputArgument::REQUIRED, 'External ID of the template questions.');
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
     * Retrieves group external ID from input and then loads its entity.
     * Throws an exception if the group does not exist.
     */
    protected function getTemplateQuestionsGroup(TemplateTest $test): TemplateQuestionsGroup
    {
        $groupEid = $this->input->getArgument('group');
        $group = $this->templatesActions->getTemplateGroup($test, $groupEid);
        if (!$group) {
            throw new RuntimeException("Template questions group '$groupEid' does not exist.");
        }
        return $group;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $questionId = $input->getArgument('externalId');

        try {
            $test = $this->getTemplateTest();
            $group = $this->getTemplateQuestionsGroup($test);

            if ($this->templatesActions->deleteQuestion($test, $group, $questionId)) {
                $output->writeln("Question '$questionId' was deleted.");
            } else {
                $output->writeln("Question '$questionId' does not exist.");
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Deletion of question '$questionId' failed: $msg");
            return Command::FAILURE;
        }
    }
}
