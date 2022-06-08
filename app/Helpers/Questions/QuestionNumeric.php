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
    private const FORMAT_DEC = 'dec';
    private const FORMAT_HEX = 'hex';
    private const FORMAT_BIN = 'bin';
    private const KNOWN_FORMATS = [ self::FORMAT_DEC, self::FORMAT_HEX, self::FORMAT_BIN ];

    /** @var int */
    private $minCount = 1;

    /** @var int */
    private $maxCount = 10;

    /** @var int[] */
    private $correct = [];

    /** @var bool */
    private $correctInOrder = true;

    /** @var string */
    private $bestFormat = self::FORMAT_DEC;

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
            'bestFormat' => Expect::string(),
        ])->skipDefaults()->castTo('array');
    }

    /**
     * Set the correct answer(s).
     * @param array $correct
     */
    public function setCorrectAnswer(array $correct): void
    {
        if (count($correct) < $this->minCount || count($correct) > $this->maxCount) {
            throw new QuestionException("The number of correct answers is out of limits.");
        }

        foreach ($correct as $c) {
            if (!is_int($c)) {
                throw new QuestionException("The correct answers must be only integers.");
            }
        }

        $this->correct = $correct;
    }

    /**
     * Configure min-max range for accepted answers (how many numbers are expected).
     * @param int $min
     * @param int $max
     */
    public function setLimits(int $min, int $max): void
    {
        if ($min > $max || $min < 0) {
            throw new QuestionException("Invalid count range [$min, $max].");
        }
        $this->minCount = $min;
        $this->maxCount = $max;
    }

    /**
     * Helper function that loads internal parameters from JSON (template or save question data).
     * @param array $json decoded input data structure
     * @param string $errorPrefix used as initial part of message if exception needs to be thrown
     */
    private function loadParameters(array $json, string $errorPrefix): void
    {
        foreach (['minCount', 'maxCount', 'correct', 'correctInOrder', 'bestFormat'] as $key) {
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
        if (!in_array($this->bestFormat, self::KNOWN_FORMATS)) {
            throw new QuestionException("$errorPrefix, unknown best format '$this->bestFormat'.");
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
     * Helper function that renders the numeric template with given parameters.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param mixed $answer
     * @param array $params
     * @return string rendered template
     */
    private function renderNumericTeplate(Engine $latte, $answer, array $params = []): string
    {
        $params['readonly'] = $params['readonly'] ?? false;
        $params['maxCount'] = $this->maxCount;

        $answerStr = [];
        foreach ($answer ?? [] as $record) {
            $answerStr[] = $record['str'] ?? $record['num'] ?? '';
        }
        $params['answer'] = $params['readonly'] && !$answerStr ? [''] : $answerStr;
        return $latte->renderToString(__DIR__ . '/templates/numeric.latte', $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderNumericTeplate($latte, $answer);
    }

    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?bool $answerIsCorrect = null
    ): string {
        $params = [ 'readonly' => true ];
        if ($answerIsCorrect !== null) {
            $params['inputClass'] = $answerIsCorrect ? 'text-success' : 'text-danger';
        }
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
            $number = (int)$number;
            return ($number !== null && $number >= -2147483648 && $number <= 2147483647) ? $number : null;
        }
        if (preg_match('/^0[xX][0-9a-fA-F]{1,8}$/', $number)) {
            return hexdec(substr($number, 2));
        }
        if (preg_match('/^0[bB][0-1]{1,32}$/', $number)) {
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
            if ($strNum === '') {
                continue; // skip empty slots
            }

            $num = self::parseNumber($strNum);
            if ($num === null) {
                return null; // invalid value renders whole input invalid
            }
            $answer[] = [
                'num' => $num,
                'str' => $strNum,
            ];
        }

        return $answer;
    }

    public function isAnswerValid($answer): bool
    {
        if (!is_array($answer) || count($answer) < $this->minCount || count($answer) > $this->maxCount) {
            return false;
        }

        foreach ($answer as $record) {
            if (!array_key_exists('num', $record) || !is_int($record['num'])) {
                return false;
            }
        }
        return true;
    }

    public function isAnswerCorrect($answer): bool
    {
        if (!$this->isAnswerValid($answer)) {
            return false;
        }

        $correct = $this->correct;
        $answerNums = array_map(function ($record) {
            return $record['num'];
        }, $answer);
        if (!$this->correctInOrder) {
            sort($correct, SORT_NUMERIC);
            sort($answerNums, SORT_NUMERIC);
        }

        foreach ($correct as $idx => $value) {
            if ($answerNums[$idx] !== $value) {
                return false;
            }
        }

        return true;
    }

    public function getCorrectAnswer()
    {
        return array_map(function ($num) {
            if ($this->bestFormat === self::FORMAT_HEX) {
                return [ 'num' => $num, 'str' => '0x' . dechex($num) ];
            } elseif ($this->bestFormat === self::FORMAT_BIN) {
                return [ 'num' => $num, 'str' => '0b' . decbin($num) ];
            }
            return [ 'num' => $num ];
        }, $this->correct);
    }
}
