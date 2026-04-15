<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\QuestionException;
use Latte\Engine;
use Exception;

/**
 * Single best answer question.
 */
final class QuestionSingle extends BaseChoiceQuestion
{
    public const TYPE = 'single';

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * Set the answers and the correct answer manually.
     * @param array $answers
     * @param int $correct
     */
    public function setAnswers(array $answers, int $correct): void
    {
        $this->setAnswersInternal($answers, $correct);
    }

    /*
     * Implementing the interface
     */

    public function instantiate($templateJson, int $seed): void
    {
        parent::instantiate($templateJson, $seed);

        try {
            $templateJson = self::normalize(self::schemaOfTemplate(), $templateJson);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, the data do not have a valid structure.", $e);
        }

        $correct = $templateJson['correct'];
        if (!is_int($correct)) {
            throw new QuestionException("Invalid question template, correct answer has invalid format.");
        }
        $answers = $this->instantiateAnswers($templateJson, [$correct]);

        $keysIndex = array_flip(array_keys($answers));
        if (!array_key_exists($correct, $keysIndex)) {
            throw new QuestionException(
                "Internal error in the process of selecting random answers, invalid index of the correct answer."
            );
        }
        $this->correct = $keysIndex[$correct];
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
    private function renderSingleChoicesTemplate(Engine $latte, string $locale, $answer, array $params = []): string
    {
        $params['type'] = 'radio';
        $params['locale'] = $locale;
        $params['answers'] = $answer !== null ? [$answer] : [];
        return $this->renderChoicesTemplate($latte, $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderSingleChoicesTemplate($latte, $locale, $answer);
    }

    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?int $mistakes = null
    ): string {
        $params = ['graded' => $mistakes === null ? 'muted' : ($mistakes === 0 ? 'success' : 'danger')];
        return $this->renderSingleChoicesTemplate($latte, $locale, $answer, $params);
    }

    public function processAnswerSubmit(array $postData)
    {
        return array_key_exists('answer', $postData) && is_numeric($postData['answer'])
            ? (int)$postData['answer'] : null;
    }

    public function isAnswerValid($answer): bool
    {
        return is_int($answer) && array_key_exists($answer, $this->answers);
    }

    public function isAnswerCorrect($answer): bool
    {
        return $this->correct !== null && $this->correct === $answer;
    }

    public function evaluateAnswer($answer): ?int
    {
        if ($this->correct === null) {
            return null;
        }

        return $this->correct === $answer ? 0 : 1;
    }
}
