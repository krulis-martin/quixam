<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\QuestionException;
use App\Helpers\Random;
use Nette\Schema\Expect;
use Latte\Engine;
use Exception;

/**
 * Question that expects one or multiple numbers as an answer.
 */
final class QuestionNumeric extends BaseQuestion
{
    /** @var int */
    protected $minCount = 1;

    /** @var int */
    protected $maxCount = 10;

    /** @var int[] */
    protected $correct = [];

    /** @var bool */
    protected $correctInOrder = true;

    /**
     * Assembles nette validation schema for question template data.
     */
    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocaizedText()->required(),
            'correct' => Expect::anyOf(Expect::int(), Expect::listOf('int'))->required(),
            'correctInOrder' => Expect::bool(),
            'minCount' => Expect::int(),
            'maxCount' => Expect::int(),
        ])->skipDefaults()->castTo('array');
    }

    /**
     * Helper function that loads internal parameters from JSON (template or save question data).
     * @param array $json decoded input data structure
     * @param string $errorPrefix used as initial part of message if exception needs to be thrown
     */
    private function loadParameters(array $json, string $errorPrefix): void
    {
        foreach (['minCount', 'maxCount', 'correct', 'correctInOrder'] as $key) {
            if (array_key_exists($key, $json)) {
                $this->$key = $json[$key];
            }
        }
        if (!is_array($this->correct)) {
            $this->correct = [ $this->correct ];
        }

        // sanity checks
        if ($this->minCount < 0) {
            throw new QuestionException("$errorPrefix, the minimal count limit must not be negative.");
        }
        if ($this->minCount > $this->maxCount) {
            throw new QuestionException("$errorPrefix, the minimal-maximal count range is inverted.");
        }
        if ($this->minCount > count($this->correct) || $this->maxCount < count($this->correct)) {
            throw new QuestionException("$errorPrefix, the correct answer is out of the minCount-maxCount range.");
        }
    }

    /*
     * Implementing the interface
     */

    public function instantiate($templateJson, int $seed): void
    {
        try {
            $templateJson = self::normalize(self::schemaOfTemplate(), $templateJson);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, the data do not have a valid structure.", $e);
        }

        parent::instantiate($templateJson, $seed);
        $this->loadParameters($templateJson, "Invalid question template");
    }

    public function load($json): void
    {
        try {
            $json = self::normalize(self::schemaOfTemplate(), $json);
        } catch (Exception $e) {
            throw new QuestionException("The question data do not have a valid structure.", $e);
        }

        parent::load($json);
        $this->loadParameters($json, "Question data are corrupted");
    }

    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        foreach (['minCount', 'maxCount', 'correct', 'correctInOrder'] as $key) {
            $json[$key] = $this->$key;
        }
        return $json;
    }

    /**
     *
     */
    private function renderNumericTeplate(Engine $latte, $answer, array $params = []): string
    {
        $params['readonly'] = $params['readonly'] ?? false;
        $params['answer'] = $answer ? $answer : [];
        $params['maxCount'] = $this->maxCount;
        return $latte->renderToString(__DIR__ . '/templates/numeric.latte', $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderNumericTeplate($latte, $answer);
    }

    public function renderResultContent(Engine $latte, string $locale, $answer = null): string
    {
        $params = [
            'readonly' => true,
            'inputClass' => $this->isAnswerCorrect($answer) ? 'text-success' : 'text-danger',
        ];
        return $this->renderNumericTeplate($latte, $answer, $params);
    }

    /**
     * Parse given string as it was a number.
     * Handles decimals, hexadecimals (0x), and binary (0b) numbers.
     * @param string $number string encoded number
     * @return int|null (null if the conversion fails)
     */
    private static function parseNumber(string $number): ?int
    {
        $number = trim($number);
        if (is_numeric($number)) {
            return (int)$number;
        }
        if (preg_match('/^0[xX][0-9a-fA-F]+$/', $number)) {
            return hexdec(substr($number, 2));
        }
        if (preg_match('/^0[bB][0-1]+$/', $number)) {
            return bindec(substr($number, 2));
        }
        return null;
    }

    public function processAnswerSubmit(array $postData)
    {
        if (empty($postData['answer'])) {
            return [];
        }
        if (!is_array($postData['answer'])) {
            return null;
        }

        $answer = [];
        foreach ($postData['answer'] as $strNum) {
            $strNum = trim($strNum);
            if (!$strNum) {
                continue; // skip empty slots
            }

            $num = self::parseNumber($strNum);
            if ($num === null) {
                return null; // invalid value renders whole input invalid
            }
            $answer[] = $num;
        }

        return $answer;
    }

    public function isAnswerValid($answer): bool
    {
        if (!is_array($answer) || count($answer) < $this->minCount || count($answer) > $this->maxCount) {
            return false;
        }

        foreach ($answer as $num) {
            if (!is_int($num)) {
                return false;
            }
        }
        return true;
    }

    public function isAnswerCorrect($answer): bool
    {
        if (!is_array($answer) || count($answer) !== count($this->correct)) {
            return false;
        }

        $correct = $this->correct;
        if (!$this->correctInOrder) {
            sort($correct, SORT_NUMERIC);
            sort($answer, SORT_NUMERIC);
        }

        foreach ($correct as $idx => $value) {
            if ($answer[$idx] !== $value) {
                return false;
            }
        }

        return true;
    }
}