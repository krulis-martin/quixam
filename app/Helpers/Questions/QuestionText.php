<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\QuestionException;
use Nette\Schema\Expect;
use Latte\Engine;
use Exception;

/**
 * Open-text questions, possibly validated by regex.
 */
final class QuestionText extends BaseQuestion
{
    private const MAX_LENGTH_LIMIT = 65000; // max MySQL text length - some overhead for json encoding and escaping

    /**
     * Maximal length of the answer.
     */
    private int $maxLength = self::MAX_LENGTH_LIMIT;

    /**
     * Regular expression that the answer must match to be considered valid. If null, no check is performed.
     */
    private ?string $regex = null;

    /**
     * Localized correct answer used as an example (for teachers).
     * @var array|string|null
     */
    private $correct = null;

    /**
     * Assembles nette validation schema for question template data.
     */
    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocalizedText()->required(),
            'correct' => BaseQuestion::schemaOfLocalizedText(),
            'maxLength' => Expect::int(),
            'regex' => Expect::string(),
        ])->skipDefaults()->castTo('array');
    }

    /**
     * Helper function that loads internal parameters from JSON (template or save question data).
     * @param array $json decoded input data structure
     * @param string $errorPrefix used as initial part of message if exception needs to be thrown
     */
    private function loadParameters(array $json, string $errorPrefix): void
    {
        foreach (['maxLength', 'regex', 'correct'] as $key) {
            if (array_key_exists($key, $json)) {
                $this->$key = $json[$key];
            }
        }

        if ($this->maxLength < 1) {
            throw new QuestionException("$errorPrefix, the maximal length must be at least 1.");
        }
        if ($this->maxLength > self::MAX_LENGTH_LIMIT) {
            throw new QuestionException("$errorPrefix, the maximal length limit is too high.");
        }

        if ($this->regex !== null && preg_match($this->regex, "") === false) {
            throw new QuestionException("$errorPrefix, the regular expression is invalid.");
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

    public function jsonSerialize(): mixed
    {
        $json = parent::jsonSerialize();
        if ($this->regex !== null) {
            $json['regex'] = $this->regex;
        }
        if ($this->maxLength < self::MAX_LENGTH_LIMIT) {
            $json['maxLength'] = $this->maxLength;
        }
        if ($this->correct !== '') {
            $json['correct'] = $this->correct;
        }
        return $json;
    }


    /**
     * Helper wrapper function for rendering single-choice template. Prepares common parameters.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      if not null, it will be used to pre-fill the last selected answer
     * @param array $params common parameters for the template
     * @return string raw HTML fragment which is pasted without escaping into the output
     */
    private function renderTextTemplate(Engine $latte, string $locale, $answer, array $params = []): string
    {
        $params['locale'] = $locale;
        $params['answer'] = $answer !== null ? (string)$answer : '';
        $params['maxLength'] = $this->maxLength;
        return $latte->renderToString(__DIR__ . '/templates/text.latte', $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderTextTemplate($latte, $locale, $answer);
    }

    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?bool $answerIsCorrect = null
    ): string {
        $params = ['graded' => $answerIsCorrect === null ? 'secondary' : ($answerIsCorrect ? 'success' : 'danger')];
        return $this->renderTextTemplate($latte, $locale, $answer, $params);
    }

    public function renderCorrectContent(Engine $latte, string $locale): string
    {
        $correctAnswer = self::getLocalizedText($this->correct, $locale);
        return $this->renderTextTemplate($latte, $locale, $correctAnswer, ['correctSample' => true]);
    }

    public function processAnswerSubmit(array $postData)
    {
        return array_key_exists('answer', $postData) && is_string($postData['answer'])
            ? $postData['answer'] : null;
    }

    public function isAnswerValid($answer): bool
    {
        return is_string($answer) && mb_strlen($answer) <= $this->maxLength && strlen(json_encode($answer)) < 65535;
    }

    public function isAnswerCorrect($answer): ?bool
    {
        if ($this->regex === null) {
            return null; // open-text questions cannot be automatically graded
        }

        return (bool)preg_match($this->regex, $answer);
    }

    public function getCorrectAnswer()
    {
        return '';
    }

    public function useRandomSeed(): bool
    {
        return false; // open questions cannot be randomized
    }
}
