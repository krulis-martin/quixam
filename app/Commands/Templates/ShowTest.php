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
 * A console command that dumps a structure of template test (groups and questions).
 */
#[AsCommand(name: 'templates:showTest', description: 'Show template test structure.')]
class ShowTemplateTest extends BaseCommand
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
        $this->addArgument('externalId', InputArgument::REQUIRED, 'External ID of the test template.');
    }

    /**
     * Retrieves test ID from input and then loads its entity.
     * Throws an exception if the test does not exist.
     */
    protected function getTemplateTest(): TemplateTest
    {
        $testExternalId = $this->input->getArgument('externalId');
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
            $result = $this->templatesActions->getTemplateTestStructure($test);
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
