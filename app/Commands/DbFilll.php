<?php

declare(strict_types=1);

namespace App\Console;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use DateTime;
use DateInterval;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * A console command that creates a new user (without password).
 */
class DbFill extends BaseCommand
{
    protected static $defaultName = 'db:fill';

    /** @var array [ entity class => repository object ] */
    private $repositories = [];

    /** @var array [ custom id => entity object ] */
    private $entities = [];

    public function __construct(...$repositories)
    {
        parent::__construct();
        foreach ($repositories as $repository) {
            $this->repositories[$repository->getEntityType()] = $repository;
        }
    }

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription('Load entities from yaml file to database.');
        $this->addArgument('file', InputArgument::REQUIRED, 'Yaml file with data to load.');
    }

    protected function getInputData()
    {
        $file = $this->input->getArgument('file');
        if (!file_exists($file)) {
            $this->output->writeln("No such file '$file' exist.");
        }

        return yaml_parse_file($file);
    }

    protected function processArgs(array $args): array
    {
        foreach ($args as &$arg) {
            if (!is_string($arg)) {
                continue;
            }

            if ($arg[0] === '#') {
                // entity object reference
                $id = substr($arg, 1);
                if (!array_key_exists($id, $this->entities)) {
                    throw new RuntimeException("Entity reference #$id not found.");
                }
                $arg = $this->entities[$id];
            } elseif (preg_match('/^@DateTime[(](?<arg>[^()]+)[)]$/', $arg, $matches)) {
                // date time
                $arg = new DateTime($matches['arg']);
            } elseif (preg_match('/^@(?<class>[a-zA-Z_]+)[(](?<id>[^()]+)[)]$/', $arg, $matches)) {
                // load of an existing entity by ID
                $class = 'App\\Model\\Entity\\' . $matches['class'];
                if (!array_key_exists($class, $this->repositories)) {
                    throw new RuntimeException("Referenced entity class '$class' not found.");
                }
                if ($matches['class'] === 'User') {
                    $arg = $this->repositories[$class]->findByEmail($matches['id']);
                    if (!$arg) {
                        throw new RuntimeException("User '$id' not found.");
                    }
                } else {
                    $arg = $this->repositories[$class]->findOrThrow($matches['id']);
                }
            }
        }

        return $args;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $yaml = $this->getInputData();
            if (!$yaml) {
                return Command::SUCCESS;
            }

            // Process command batches in entire file..
            foreach ($yaml as $commands) {
                if (!is_array($commands) || count($commands) !== 1) {
                    throw new RuntimeException("Invalid command batch found '$command'.");
                }
                $id = key($commands); // identifies entity object
                $commands = reset($commands); // set of methods called on the object

                if (!array_key_exists($id, $this->entities)) {
                    // create a new instance by calling constructor first
                    $constructor = array_shift($commands);
                    if (!is_array($constructor) || count($constructor) !== 1) {
                        throw new RuntimeException("Invalid constructor found '$constructor'.");
                    }

                    $args = reset($constructor);
                    $class = 'App\\Model\\Entity\\' . key($constructor);

                    if (!array_key_exists($class, $this->repositories)) {
                        $output->writeln("Unknown entity class '$class'.");
                        return Command::FAILURE;
                    }

                    $args = $this->processArgs($args);
                    $obj = new $class(...$args);

                    $this->entities[$id] = $obj;
                } else {
                    $obj = $this->entities[$id];
                }

                // call additional methods on the entity
                foreach ($commands as $command) {
                    if (!is_array($command) || count($command) !== 1) {
                        throw new RuntimeException("Invalid command found '$command'.");
                    }
                    $args = $this->processArgs(reset($command));
                    $method = key($command);
                    $obj->$method(...$args);
                }

                // persist the entity
                $class = get_class($obj);
                $this->repositories[$class]->persist($obj);
            }

            foreach ($this->entities as $id => $entity) {
                $class = get_class($entity);
                $eid = $entity->getId();
                $output->writeln("$id: $class($eid)");
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
