<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
use App\Helpers\QuestionFactory;
use App\Helpers\DynamicQuestion;
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
    /** @var TemplateTests */
    private $templateTests;

    /** @var TemplateQuestions */
    private $templateQuestions;

    /** @var TemplateQuestionsGroups */
    private $templateQuestionsGroups;

    /** @var QuestionFactory */
    private $questionFactory;

    public function __construct(
        TemplateTests $templateTests,
        TemplateQuestions $templateQuestions,
        TemplateQuestionsGroups $templateQuestionsGroups,
        QuestionFactory $questionFactory
    ) {
        parent::__construct();
        $this->templateTests = $templateTests;
        $this->templateQuestions = $templateQuestions;
        $this->templateQuestionsGroups = $templateQuestionsGroups;
        $this->questionFactory = $questionFactory;
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
        $test = $this->templateTests->findOneBy(['externalId' => $testExternalId]);
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
        $group = $this->templateQuestionsGroups->findOneBy(['test' => $test->getId(), 'externalId' => $groupEid]);
        if (!$group) {
            throw new RuntimeException("Template questions group '$groupEid' does not exist.");
        }
        return $group;
    }

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

    protected function getQuestionData()
    {
        $filePath = $this->input->getArgument('dataFile');
        if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("File '$filePath' does not exist.");
        }

        $data = json_decode(file_get_contents($filePath), true);

        $type = $this->input->getOption('type') ?? '';

        // smoke tests
        if ($type) {
            // regular (static) question
            $questionData = $this->questionFactory->create($type);
            for ($seed = 0; $seed < 20; ++$seed) {
                $questionData->instantiate($data, $seed); // throws on error
            }
        } else {
            // dynamic (generated question)
            $dynamicQuestion = new DynamicQuestion($data, $this->questionFactory);
            $res = DynamicQuestion::validateCode($dynamicQuestion->getCode());
            if ($res !== true) {
                foreach (is_array($res) ? $res : [] as $error) {
                    echo "Code validation error: $error\n";
                }
                throw new RuntimeException("Code validation of dynamic question generator failed.");
            }

            $dynamicQuestion->generate(42);

            for ($seed = 0; $seed < 20; ++$seed) {
                $dynamicQuestion = new DynamicQuestion($data, $this->questionFactory);
                $dynamicQuestion->generate($seed);
            }
        }

        return [$type, $data];
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

            // prepare question data
            $caption = $this->getCaption();
            $captionJson = json_encode($caption);
            [$type, $data] = $this->getQuestionData();
            $dataJson = $data === null ? '' : json_encode($data);

            if (
                $question
                && $question->getCaptionRaw() === $captionJson
                && $question->getType() === $type
                && $question->getDataRaw() === $dataJson
            ) {
                $output->writeln("Nothing changed in question '$questionId', ending without update.");
                return Command::SUCCESS;
            }

            $output->writeln("Creating '$questionId' template question ...");
            $newQuestion = new TemplateQuestion($group, $type, $caption, $data, $questionId, $question);
            $this->templateQuestions->persist($newQuestion);

            if (!empty($question)) {
                $this->templateQuestions->remove($question);
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
