<?php

declare(strict_types=1);

namespace Quixam;

use RuntimeException;

/**
 * A REST API client for Quixam.
 */
final class CliApiClient implements IApiClient
{
    /** @var string */
    private $quixamBin;

    private $tmpDir = __DIR__ . '/../.tmp';

    /**
     * Initialize adapter by setting base dir where question files are and path to quixam instance.
     */
    public function __construct(string $quixamPath)
    {
        $bin = realpath("$quixamPath/bin/console");
        if (!$bin || !file_exists($bin) || !is_file($bin)) {
            throw new RuntimeException("Path '$quixamPath' is not valid path to Quixam instance.");
        }
        $this->quixamBin = $bin;
    }

    /**
     * Invokes given command (args) on Quixam CLI interface.
     * @return string|false Output contents of false on failure.
     */
    private function execQuixamConsole(array $args)
    {
        array_unshift($args, $this->quixamBin);
        foreach ($args as &$arg) {
            $arg = escapeshellarg($arg);
        }
        array_unshift($args, 'php');
        $args = join(' ', $args);
        exec($args, $output, $res);
        if (is_array($output)) {
            $output = join("\n", $output);
        }

        if ($res !== 0) {
            echo "Error: $this->quixamBin exited with code $res\n$output\n";
            return false;
        }
        return $output;
    }

    /**
     * Converts given question object into JSON data file as expected by Quixam CLI.
     * @return string path to the JSON file in tmp directory
     */
    private function prepareDataFile(string $groupId, string $questionId, array $data): string
    {
        $dataFileDir = "$this->tmpDir/$groupId";
        @mkdir($dataFileDir, 0755, true);
        $file = "$dataFileDir/$questionId.json";
        file_put_contents($file, json_encode($data));
        return $file;
    }

    public function getTestStructure(string $testId): ?array
    {
        $content = $this->execQuixamConsole(['templates:showTest', $testId]);
        $json = $content ? json_decode($content, true) : null;
        if ($json === false) {
            throw new RuntimeException("Unable to read current content of the test template!");
        }
        return $json;
    }

    /**
     * Invokes add group via Quixam CLI.
     */
    public function addGroup(string $testId, string $groupId, int $points, int $ordering): void
    {
        $args = [
            'templates:addGroup',
            '--points',
            $points,
            '--ordering',
            $ordering,
            '--count',
            1,
            $testId,
            $groupId,
        ];
        $res = $this->execQuixamConsole($args);
        if ($res === false) {
            throw new RuntimeException("Creation/update of group $groupId failed!");
        }
    }

    /**
     * Prepare question json file and invokes add question via Quixam CLI.
     */
    public function addQuestion(
        string $testId,
        string $groupId,
        string $questionId,
        string $type,
        string $caption_en,
        string $caption_cs,
        array $data
    ): void {
        $args = [
            'templates:addQuestion',
            '--caption_en',
            $caption_en,
        ];
        if ($type) {
            $args[] = '--type';
            $args[] = $type;
        }
        if ($caption_cs) {
            array_push($args, '--caption_cs', $caption_cs);
        }
        array_push($args, $testId, $groupId, $questionId);
        array_push($args, $this->prepareDataFile($groupId, $questionId, $data));

        $res = $this->execQuixamConsole($args);
        if ($res === false) {
            throw new RuntimeException("Creation/update of question $groupId/$questionId failed!");
        }
    }

    public function deleteGroup(string $testId, string $groupId): void
    {
        $res = $this->execQuixamConsole(['templates:deleteGroup', $testId, $groupId]);
        if ($res === false) {
            throw new RuntimeException("Deletion of group $groupId failed!");
        }
    }

    public function deleteQuestion(string $testId, string $groupId, string $questionId): void
    {
        $res = $this->execQuixamConsole(['templates:deleteQuestion', $testId, $groupId, $questionId]);
        if ($res === false) {
            throw new RuntimeException("Deletion of question $groupId/$questionId failed!");
        }
    }
}
