<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\TemplateTest;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestionsGroups;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Exception;
use RuntimeException;

/**
 * A console command that soft-deletes given template group.
 */
#[AsCommand(name: 'templates:deleteGroup', description: 'Soft-delete template questions group.')]
class DeleteGroupTemplate extends BaseCommand
{
    /** @var TemplateTests */
    private $templateTests;

    /** @var TemplateQuestionsGroups */
    private $templateQuestionsGroups;

    public function __construct(
        TemplateTests $templateTests,
        TemplateQuestionsGroups $templateQuestionsGroups
    ) {
        parent::__construct();
        $this->templateTests = $templateTests;
        $this->templateQuestionsGroups = $templateQuestionsGroups;
    }

    protected function configure()
    {
        $this->addArgument('test', InputArgument::REQUIRED, 'External ID of the test template.');
        $this->addArgument('externalId', InputArgument::REQUIRED, 'External ID of the template questions group.');
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $test = $this->getTemplateTest();

            $groupId = $input->getArgument('externalId');
            $group = $this->templateQuestionsGroups->findOneBy(['externalId' => $groupId, 'test' => $test->getId()]);

            if (!$group) {
                $output->writeln("Questions group '$groupId' does not exist.");
                return Command::SUCCESS;
            }

            $this->templateQuestionsGroups->remove($group);
            $this->templateQuestionsGroups->flush();
            $output->writeln("Questions group '$groupId' was deleted.");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stderr->writeln("Error: $msg");
            return Command::FAILURE;
        }
    }
}
