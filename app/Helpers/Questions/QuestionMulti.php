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
 * Single best answer question.
 */
final class QuestionMulti extends BaseChoiceQuestion
{
    /*
     * Implementing the interface
     */

    public function instantiate($templateJson, int $seed): void
    {
        parent::instantiate($templateJson, $seed);

        try {
            $templateJson = self::normalize(self::schemaOfTemplate(), $templateJson);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, answer text does not have valid format.", $e);
        }

        if (!is_array($templateJson['correct'])) {
            throw new QuestionException("Invalid question template, correct answer has invalid format.");
        }

        $answers = $this->instantiateAnswers($templateJson);

        $this->answers = array_values($answers);
        $keysIndex = array_flip(array_keys($answers));

        $correct = [];
        foreach ($templateJson['correct'] as $cKey) {
            // include only sub-set of correct answers that were actually selected...
            if (array_key_exists($cKey, $keysIndex)) {
                $correct[$keysIndex[$cKey]] = true; // using it as key also deduplicates the correct answers
            }
        }

        $this->correct = array_keys($correct);
    }

    /**
     * Helper wrapper function for rendering multi-choice template. Prepares common paramters.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      if not null, it will be used to pre-fill the last selected answer
     * @param array $params common parameters for the template
     * @return string raw HTML fragment which is pasted without excaping into the output
     */
    private function renderMultiChoicesTeplate(Engine $latte, string $locale, $answer, array $params = []): string
    {
        $params['type'] = 'checkbox';
        $params['locale'] = $locale;
        $params['answers'] = $answer !== null ? $answer : [];
        return $this->renderChoicesTeplate($latte, $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderMultiChoicesTeplate($latte, $locale, $answer);
    }

    public function renderResultContent(Engine $latte, string $locale, $answer = null): string
    {
        $params = [ 'graded' => $this->isAnswerCorrect($answer) ? 'success' : 'danger' ];
        return $this->renderMultiChoicesTeplate($latte, $locale, $answer, $params);
    }

    public function processAnswerSubmit(array $postData)
    {
        if (!array_key_exists('answer', $postData)) {
            return [];
        }

        if (!is_array($postData['answer'])) {
            return null;
        }

        $answer = $postData['answer'];
        foreach ($answer as &$idx) {
            if (!is_numeric($idx)) {
                return null;
            }
            $idx = (int)$idx;
        }

        return $answer;
    }

    public function isAnswerValid($answer): bool
    {
        if (!is_array($answer)) {
            return false;
        }

        foreach ($answer as $key) {
            if (!is_int($key) || !array_key_exists($key, $this->answers)) {
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

        foreach ($this->correct as $correct) {
            if (!in_array($correct, $answer)) {
                return false;
            }
        }

        return true;
    }
}
