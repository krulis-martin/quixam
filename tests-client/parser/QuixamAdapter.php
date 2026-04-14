<?php

namespace Quixam;

use Parser\Parser;
use Parser\TextFile;
use Parser\ParsedQuestion;
use RuntimeException;

/**
 * Adapter for Quixam CLI which feeds the templates to the system (and removes unused ones).
 */
class Adapter
{
    /** @var IApiClient */
    private $client;

    /** @var string|null */
    private $baseDir = null;

    /** @var string|null */
    private $testId = null;

    /** @var array */
    private $groups = [];

    /** @var Parser|null */
    private $parser = null;

    /**
     * Initialize adapter by setting base dir where question files are and path to quixam instance.
     */
    public function __construct(IApiClient $client, ?string $baseDir = null)
    {
        $this->client = $client;

        if ($baseDir) {
            $this->baseDir = realpath($baseDir);
            if (!$this->baseDir || !is_dir($this->baseDir)) {
                throw new RuntimeException("Base directory '$baseDir' not found.");
            }
        }

        // the parser is initialize here, since it requires no configuration and can be used in the whole class
        // in the future, this may be replaced with DI pattern, if needed
        $this->parser = new Parser();
    }

    /**
     * Load yaml configuration of the test.
     * It also sets the base path for the question files (base dir of the config file).
     * @param string $config path to the yaml config file
     */
    public function loadConfig(string $config): void
    {
        if (!file_exists($config) || !is_file($config) || !is_readable($config)) {
            throw new RuntimeException("File '$config' does not exist.");
        }

        $yaml = \yaml_parse_file($config);
        if (!$yaml || !is_array($yaml) || empty($yaml['id']) || empty($yaml['groups'])) {
            throw new RuntimeException("File '$config' is not valid yaml config file.");
        }

        $this->testId = $yaml['id'];
        $this->groups = $yaml['groups'];
        foreach ($this->groups as $group => $data) {
            if (array_key_exists('points', $data) && !is_numeric($data['points'])) {
                throw new RuntimeException("Group '$group' has invalid points value '{$data['points']}'.");
            }
            if (
                array_key_exists('pointsPerItem', $data) && !is_numeric($data['pointsPerItem'])
                && $data['pointsPerItem'] !== null
            ) {
                throw new RuntimeException(
                    "Group '$group' has invalid pointsPerItem value '{$data['pointsPerItem']}'."
                );
            }
            if (array_key_exists('count', $data) && !is_numeric($data['count'])) {
                throw new RuntimeException("Group '$group' has invalid count value '{$data['count']}'.");
            }
            if (!array_key_exists('count', $data)) {
                $this->groups[$group]['count'] = 1;
            }
        }

        $this->baseDir = realpath(dirname($config));
    }

    private static function getNumType(string $number)
    {
        $types = [
            'hex' => '/^0[xX][0-9a-fA-F]+$/',
            'bin' => '/^0[bB][0-1]+$/',
        ];
        foreach ($types as $type => $regex) {
            if (preg_match($regex, $number)) {
                return $type;
            }
        }
        return 'dec';
    }

    private static function parseNum(string $number): int
    {
        $number = trim($number);
        if (is_numeric($number)) {
            return (int)$number;
        }
        $type = self::getNumType($number);
        if ($type === 'hex' || $type === 'bin') {
            $fnc = $type . 'dec'; // hexdec or bindec
            return $fnc(substr($number, 2));
        }
        throw new RuntimeException("Value '$number' was not recognized as a number.");
    }

    private static function greatestKey(array $array)
    {
        $best = key($array);
        foreach ($array as $key => $value) {
            if ($value > $array[$best]) {
                $best = $key;
            }
        }
        return $best;
    }

    /**
     * Prepare data for the choice questions (single and multi). These questions have a list of answers,
     * and one (single), or a list of (multi) correct answer(s).
     * The question is expected one mandatory parameter (number of answers).
     * The answers are encoded in correct, answers, and optionally exclusiveAnswers sections.
     * @param ParsedQuestion $question parsed question data
     * @param array $json reference to the JSON data being prepared (will be updated)
     */
    private function prepareChoiceData(ParsedQuestion $question, array &$json): void
    {
        if (count($question->parameters) !== 1) {
            throw new RuntimeException(
                "The $question->type question expects exactly one parameter in the @question tag!"
            );
        }

        if (!is_numeric($question->parameters[0]) || (int)$question->parameters[0] < 1) {
            throw new RuntimeException(
                "The parameter of $question->type question is expected to be a positive integer!"
            );
        }

        $json['count'] = (int)$question->parameters[0];
        $json['answers'] = $question->answers;
        $json['correct'] = $question->correct;
        if ($question->type === 'single') {
            if (count($json['correct']) !== 1) {
                throw new RuntimeException(
                    "ParsedQuestion type '$question->type' is expected to have exactly one correct answer!"
                );
            }
            $json['correct'] = reset($json['correct']);
        }

        // exclusive answers hold pairs of mutually exclusive correct-wrong answers (as indices into answers array)
        $json['exclusive'] = $question->exclusiveAnswers;
    }

    /**
     * Prepare data for numeric questions. The correct answers are encoded as question parameters.
     * No other sections (except for text) are expected for numeric questions.
     * @param ParsedQuestion $question parsed question data
     * @param array $json reference to the JSON data being prepared (will be updated)
     */
    private function prepareNumericData(ParsedQuestion $question, array &$json): void
    {
        // numeric questions
        $json['correct'] = [];
        $formats = ['dec' => 0, 'hex' => 0, 'bin' => 0];
        foreach ($question->parameters as $param) {
            $json['correct'][] = self::parseNum($param);
            $formats[self::getNumType($param)]++;
        }
        $json['correctInOrder'] = true;
        $json['minCount'] = 1;
        if ($question->type === 'num') {
            $json['maxCount'] = 1; // only one number is allowed
        }

        if ($json['minCount'] > count($json['correct'])) {
            throw new RuntimeException("At least " . $json['minCount']
                . " numbers are required, but the correct answer has only " . count($json['correct']));
        }
        if (($json['maxCount'] ?? 10) < count($json['correct'])) {
            throw new RuntimeException("At most " . $json['maxCount']
                . " numbers are allowed, but the correct answer has " . count($json['correct']));
        }

        $json['bestFormat'] = self::greatestKey($formats);
    }

    /**
     * Prepare data for the order question. The question is expected to have two numeric parameters:
     * - min number of items to be ordered (positive integer)
     * - max number of items to be ordered (positive integer)
     * @param ParsedQuestion $question parsed question data
     * @param array $json reference to the JSON data being prepared (will be updated)
     */
    private function prepareOrderData(ParsedQuestion $question, array &$json): void
    {
        if (
            count($question->parameters) !== 2 || !is_numeric($question->parameters[0])
            || !is_numeric($question->parameters[1])
        ) {
            throw new RuntimeException(
                "ParsedQuestion type '$question->type' is expected to have exactly two numeric parameters (min, max)!"
            );
        }
        $min = $json['minCount'] = self::parseNum($question->parameters[0]);
        $max = $json['maxCount'] = self::parseNum($question->parameters[1]);
        $json['items'] = $question->items;
        $count = count($question->items);

        // sanity checks
        $mandatory = 0;
        foreach ($question->items as $item) {
            $mandatory += ($item['mandatory'] ?? false) ? 1 : 0;
        }

        if ($min < 1 || $max > $min || $max > $count) {
            throw new RuntimeException(
                "Invalid min-max limits [$min,$max] of order question. They must be within [1,$count] range."
            );
        }
        if ($min > $mandatory) {
            throw new RuntimeException(
                "At least $min items are required, but the question has only $mandatory mandatory answers."
            );
        }
        if ($max < $mandatory) {
            throw new RuntimeException(
                "At most $max items are allowed, but the question has $mandatory mandatory answers."
            );
        }
    }

    /**
     * Prepare data for the text question. Text question has two optional parameters:
     * - max length of the answer (positive integer)
     * - regular expression that the answer must match (string)
     * Optionally, it may contain one @correct section with a sample correct answer (string).
     * It must not contain any other parameters or sections.
     * @param ParsedQuestion $question parsed question data
     * @param array $json reference to the JSON data being prepared (will be updated)
     */
    private function prepareTextData(ParsedQuestion $question, array &$json): void
    {
        if (count($question->parameters) > 2) {
            throw new RuntimeException(
                "Text question expects at most two parameters in the @question tag!"
            );
        }
        if (count($question->parameters) >= 1) {
            if (!is_numeric($question->parameters[0]) || (int)$question->parameters[0] < 1) {
                throw new RuntimeException(
                    "The first parameter (max length) of text question is expected to be a positive integer!"
                );
            }
            $json['maxLength'] = (int)$question->parameters[0];
        }

        if (count($question->parameters) === 2) {
            if (!is_string($question->parameters[1]) || !preg_match($question->parameters[1], '') === false) {
                throw new RuntimeException(
                    "The second parameter of text question is expected to be a valid regular expression!"
                );
            }
            $json['regex'] = $question->parameters[1];
        }

        // the correct answer is optional for text questions, but if provided, it must be a string
        if (count($question->correct) > 1 || count($question->correct) !== count($question->answers)) {
            throw new RuntimeException("Text question is expected to have at most one correct answer!");
        }

        // if there is an answer, it is the correct answer
        if (count($question->answers) === 1 && $question->answers[0]) {
            $json['correct'] = $question->answers[0]; // copy of the correct answer
        }
    }

    private const FORBIDDEN_SECTIONS = [
        'dynamic' => ['answers', 'correct', 'exclusiveAnswers', 'items'],
        'single' => ['code', 'items'],
        'multi' => ['code', 'items'],
        'num' => ['answers', 'correct', 'exclusiveAnswers', 'code', 'items'],
        'nums' => ['answers', 'correct', 'exclusiveAnswers', 'code', 'items'],
        'order' => ['answers', 'correct', 'exclusiveAnswers', 'code'],
        'text' => ["exclusiveAnswers", "code", "items"]
    ];

    /**
     * Converts given question object into JSON data file as expected by Quixam API.
     * This is the most essential part of the adapter which handles the necessary data conversions
     * (adapting the question file format to API format).
     * @param ParsedQuestion $question the question object to be uploaded
     * @return array containing the JSON data
     */
    private function prepareData(ParsedQuestion $question): array
    {
        // basic checks that forbidden sections are empty
        foreach (self::FORBIDDEN_SECTIONS[$question->type] ?? [] as $key) {
            if (count($question->$key) > 0) {
                throw new RuntimeException(
                    "ParsedQuestion type '$question->type' is not expected to have any '$key' sections!"
                );
            }
        }

        // text is common to all
        $json = ['text' => $question->text];

        // prepare remaining data based on the question type
        if ($question->type === 'dynamic') {
            $code = trim($question->getCode());
            if (!$code) {
                throw new RuntimeException("Dynamic question must have some code, but none provided.");
            }
            $json['code'] = $code;
        } elseif ($question->type === 'single' || $question->type === 'multi') {
            $this->prepareChoiceData($question, $json);
        } elseif ($question->type === 'num' || $question->type === 'nums') {
            $this->prepareNumericData($question, $json);
        } elseif ($question->type === 'order') {
            $this->prepareOrderData($question, $json);
        } elseif ($question->type === 'text') {
            $this->prepareTextData($question, $json);
        } else {
            throw new RuntimeException("ParsedQuestion type '$question->type' is not supported yet!");
        }

        return $json;
    }

    /**
     * Handles the necessary data conversions and preparations and invokes the addQuestion method of the client.
     * @param string $groupId external ID of the question group
     * @param string $questionId external ID of the question
     * @param ParsedQuestion $question the question object to be uploaded
     * @throws RuntimeException if the question type is not supported or if the question data is invalid
     */
    private function addQuestion(string $groupId, string $questionId, ParsedQuestion $question): void
    {
        $typeMappings = [
            'single' => 'single',
            'multi' => 'multi',
            'num' => 'numeric',
            'nums' => 'numeric',
            'order' => 'order',
            'text' => 'text',
            'dynamic' => '', // dynamic questions do not have a type
        ];
        if (!array_key_exists($question->type, $typeMappings)) {
            throw new RuntimeException("Invalid type '$question->type' of question $groupId/$questionId!");
        }

        $this->client->addQuestion(
            $this->testId,
            $groupId,
            $questionId,
            $typeMappings[$question->type],
            $question->title->getText('en'),
            $question->title->hasLocale('cs') ? $question->title->getText('cs') : null,
            $this->prepareData($question)
        );
    }

    /**
     * Scan the directory and file structure and generate array representing groups and questions.
     * @return array [ groupId => [ questionIds ] ]
     */
    private function getStructure(): array
    {
        $structure = [];
        foreach (array_keys($this->groups) as $group) {
            $dir = $this->baseDir . '/' . $group;
            if (!is_dir($dir)) {
                throw new RuntimeException("Group $group does not have a sub-directory.");
            }

            $files = [];
            foreach (glob("$dir/*.md") as $file) {
                $files[] = basename($file, '.md');
            }
            $structure[$group] = $files;
        }
        return $structure;
    }

    /**
     * Uploads questions of one single group.
     * @param string $groupId
     * @param string[] $questions (only IDs)
     * @param string[] $currentQuestions (only IDs)
     */
    private function uploadGroup(string $groupId, array $questions, array $currentQuestions): void
    {
        foreach ($currentQuestions as $questionId) {
            if (!in_array($questionId, $questions)) {
                echo "\tQuestion $questionId is no longer present in group $groupId, removing it...\n";
                $this->client->deleteQuestion($this->testId, $groupId, $questionId);
            }
        }

        foreach ($questions as $questionId) {
            echo "\tUploading question $questionId ...\n";
            $fileName = "$this->baseDir/$groupId/$questionId.md";
            $question = $this->parser->parseFile(TextFile::load($fileName));
            if (!$question) {
                echo "Parsing of $fileName failed!\n";
                continue;
            }
            $this->addQuestion($groupId, $questionId, $question);
        }
    }

    /**
     * The main action performed by this adapter. Scans all configured directories and uploads all the questions.
     */
    public function upload(): void
    {
        $structure = $this->getStructure();
        $current = $this->client->getTestStructure($this->testId) ?? [];

        foreach (array_keys($current) as $group) {
            if (!array_key_exists($group, $structure)) {
                echo "Group $group is no longer present, removing it...\n";
                $this->client->deleteGroup($this->testId, $group);
            }
        }

        $ordering = 0;
        foreach ($structure as $group => $questions) {
            echo "Uploading questions of $group ...\n";
            $points = $this->groups[$group]['points'] ?? 1;
            $pointsPerItem = (int)$this->groups[$group]['pointsPerItem'] ?? 0;
            $count = $this->groups[$group]['count'] ?? 1;
            if ($count > count($questions)) {
                echo "Warning: group $group selects $count questions, but only " . count($questions)
                    . " provided. Restricting selection to " . count($questions) . ".\n";
                $count = count($questions);
            }

            $this->client->addGroup($this->testId, $group, $points, $pointsPerItem, $count, ++$ordering);
            $this->uploadGroup($group, $questions, $current[$group] ?? []);
        }
    }
}
