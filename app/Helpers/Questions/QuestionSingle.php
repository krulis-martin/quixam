<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\QuestionException;
use App\Helpers\Random;
use Nette\Schema\Expect;
use Exception;

/**
 * Single best answer question.
 */
final class QuestionSingle extends BaseQuestion
{
    private $answers = [];
    private $correct = null;

    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocaizedText()->required(),
            'answers' => Expect::listOf(BaseQuestion::schemaOfLocaizedText())->required(),
            'count' => Expect::int()->required(),
            'correct' => Expect::int()->required(),
            'exclusive' => Expect::listOf(Expect::listOf('int')),
        ])->castTo('array');
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
            throw new QuestionException("Invalid question template, answer text does not have valid format.", $e);
        }

        $count = $templateJson['count'];
        $correct = $templateJson['correct'];
        $allAnswers = $this->loadAnswers($templateJson['answers'], 'Invalid question template');
        $exclusive = $templateJson['exclusive'];
        if (!array_key_exists($correct, $allAnswers)) {
            throw new QuestionException("Invalid question template, no correct answer is specified.");
        }

        try {
            $answers = Random::selectRandomSubset($allAnswers, $count, [ $correct ], $exclusive);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, model answers are not correctly defined.", $e);
        }

        $this->answers = array_values($answers);
        $keysIndex = array_flip(array_keys($answers));
        if (!array_key_exists($correct, $keysIndex)) {
            throw new QuestionException(
                "Internal error in the process of selecting random answers, invalid index of the correct answer."
            );
        }
        $this->correct = $keysIndex[$correct];
    }

    public function load($json): void
    {
        parent::load($json);

        if (!array_key_exists('answers', $json) || !is_array($json['answers'])) {
            throw new QuestionException("Corrupted question data, propery 'answers' is missing or has invalid value.");
        }

        $this->answers = $this->loadAnswers($json['answers'], 'Corrupted question data');

        if (!array_key_exists('correct', $json) || !$this->verifyAnswer($json['correct'])) {
            throw new QuestionException("Corrupted question data, propery 'correct' is missing or has invalid value.");
        }
        $this->correct = $json['correct'];
    }

    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        $json['answers'] = $this->answers;
        $json['correct'] = $this->correct;
    }

    public function renderFormContent($answer = null): string
    {
        return 'TODO';
    }

    public function renderResultContent($answer = null): string
    {
        return 'TODO';
    }

    public function verifyAnswer($answer): bool
    {
        return $answer === null || (is_int($answer) && array_key_exists($answer, $this->answers));
    }

    public function isAnswerCorrect($answer): bool
    {
        return $this->correct !== null && $this->correct === $answer;
    }
}
