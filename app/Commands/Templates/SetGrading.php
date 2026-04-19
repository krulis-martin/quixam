<?php

declare(strict_types=1);

namespace App\Console;

use App\Helpers\TemplatesActions;
use App\Model\Entity\TemplateTest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;
use RuntimeException;

/**
 * A console command that sets the grading configuration for a test template.
 */
#[AsCommand(name: 'templates:setGrading', description: 'Set grading configuration for a test template.')]
class SetGradingTemplate extends BaseCommand
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
        $this->addArgument('grading', InputArgument::REQUIRED, 'Grading configuration as JSON object.');
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $test = $this->getTemplateTest();

            $grading = json_decode(trim($this->input->getArgument('grading')), true);
            if (!is_array($grading)) {
                $output->writeln("Unable to parse grading data from input. Please provide valid JSON array.");
                return Command::FAILURE;
            }

            $output->writeln("Setting grading configuration for '{$test->getExternalId()}' test template...");
            $this->templatesActions->setGrading($test, $grading);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Setting grading configuration failed: $msg");
            return Command::FAILURE;
        }
    }
}
