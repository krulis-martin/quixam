<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestion;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Repository\TemplateTests;
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
 * A console command that dumps a structure of template test (groups and questions).
 */
class ShowTemplateTest extends BaseCommand
{
    protected static $defaultName = 'templates:showTest';

    /** @var TemplateTests */
    private $templateTests;

    public function __construct(TemplateTests $templateTests)
    {
        parent::__construct();
        $this->templateTests = $templateTests;
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription('Add/update template questions group.');
        $this->addArgument('externalId', InputArgument::REQUIRED, 'External ID of the test template.');
    }

    /**
     * Retrieves test ID from input and then loads its entity.
     * Throws an exception if the test does not exist.
     */
    protected function getTemplateTest(): TemplateTest
    {
        $testExternalId = $this->input->getArgument('externalId');
        $test = $this->templateTests->findOneBy([ 'externalId' => $testExternalId ]);
        if (!$test) {
            throw new RuntimeException("Test template '$testExternalId' does not exist.");
        }
        return $test;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $test = $this->getTemplateTest();

            $result = [];
            foreach ($test->getQuestionGroups() as $group) {
                $questions = [];
                foreach ($group->getQuestions() as $question) {
                    $questions[] = $question->getExternalId() ?? $question->getId();
                }
                $result[$group->getExternalId() ?? $group->getId()] = $questions;
            }

            $output->writeln(json_encode($result));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }
    }
}
