<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
use DateTime;
use DateInterval;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * A console command that soft-deletes given template question.
 */
class DeleteQuestionTemplate extends BaseCommand
{
    protected static $defaultName = 'templates:deleteQuestion';

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
        $this->setName(self::$defaultName)->setDescription('Soft-delete template question.');
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
        $test = $this->templateTests->findOneBy([ 'externalId' => $testExternalId ]);
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
        $group = $this->templateQuestionsGroups->findOneBy([ 'test' => $test->getId(), 'externalId' => $groupEid ]);
        if (!$group) {
            throw new RuntimeException("Template questions group '$groupEid' does not exist.");
        }
        return $group;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $test = $this->getTemplateTest();
            $group = $this->getTemplateQuestionsGroup($test);

            $questionId = $input->getArgument('externalId');
            $question = $this->templateQuestions->findOneBy([
                'externalId' => $questionId,
                'test' => $test->getId(),
                'questionsGroup' => $group->getId()
            ]);

            if (!$question) {
                $output->writeln("Question '$questionId' does not exist.");
                return Command::SUCCESS;
            }

            $this->templateQuestions->remove($question);
            $this->templateQuestions->flush();
            $output->writeln("Question '$questionId' was deleted.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }
    }
}
