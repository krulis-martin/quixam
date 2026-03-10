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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;
use RuntimeException;

/**
 * A console command that creates or replaces template question.
 */
#[AsCommand(name: 'templates:addQuestion', description: 'Add/update template question.')]
class AddQuestionTemplate extends BaseCommand
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
        $this->addArgument('dataFile', InputArgument::REQUIRED, 'Path to JSON data file.');
        $this->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Question type');
        $this->addOption('caption_en', null, InputOption::VALUE_OPTIONAL, 'English translation of the caption.');
        $this->addOption('caption_cs', null, InputOption::VALUE_OPTIONAL, 'Czech translation of the caption.');
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

    /**
     * Get caption options and assemble localized caption array.
     * @return array [ 'en' => 'English caption', 'cs' => 'Czech caption', ... ]
     */
    protected function getCaption(): array
    {
        if ($this->input->getOption('caption_en') === null) {
            throw new RuntimeException("English caption must be present.");
        }

        $caption = ['en' => trim($this->input->getOption('caption_en'))];
        if ($this->input->getOption('caption_cs') !== null) {
            $caption['cs'] = trim($this->input->getOption('caption_cs'));
        }
        return $caption;
    }

    /**
     * Retrieves question data from a JSON file specified in input and checks its validity.
     * @return array [ $type, $data ] pair
     */
    protected function getQuestionData(): array
    {
        $filePath = $this->input->getArgument('dataFile');
        if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("File '$filePath' does not exist.");
        }

        $data = json_decode(file_get_contents($filePath), true);
        $type = $this->input->getOption('type') ?? '';

        // smoke tests (we do not want to upload anything invalid)
        $this->templatesActions->checkQuestionData($type, $data);

        return [$type, $data];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $questionId = $input->getArgument('externalId');

        try {
            $test = $this->getTemplateTest();
            $group = $this->getTemplateQuestionsGroup($test);

            [$type, $data] = $this->getQuestionData();
            $res = $this->templatesActions->addQuestion(
                $test,
                $group,
                $questionId,
                $this->getCaption(),
                $type,
                $data,
            );

            if ($res === null) {
                $output->writeln("Creating new '$questionId' template question ...");
            } elseif ($res === false) {
                $output->writeln("Replacing existing '$questionId' template question ...");
            } else {
                $output->writeln("Nothing changed in question '$questionId', ending without update.");
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Adding '$questionId' question template failed: $msg");
            return Command::FAILURE;
        }
    }
}
