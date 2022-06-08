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
 * A common base for a sub-group of questions that have fixed list of possible options (answers).
 */
abstract class BaseChoiceQuestion extends BaseQuestion
{
    /** @var array */
    protected $answers = [];

    /** @var null|int|int[] */
    protected $correct = null;

    /**
     * Assembles nette validation schema for question template data.
     */
    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocaizedText()->required(),
            'answers' => Expect::listOf(BaseQuestion::schemaOfLocaizedText())->required(),
            'count' => Expect::int()->required(),
            'correct' => Expect::anyOf(Expect::int(), Expect::listOf('int'))->required(),
            'exclusive' => Expect::listOf(Expect::listOf('int')),
        ])->castTo('array');
    }

    /**
     * Set the answers and the correct answer(s) manually.
     * @param array $answers
     * @param int|int[] $correct
     */
    protected function setAnswersInternal(array $answers, $correct): void
    {
        try {
            $this->answers = $this->loadAnswers($answers, 'Error');
        } catch (Exception $e) {
            throw new QuestionException("Unable to set answers, invalid format detected.", $e);
        }

        foreach (is_array($correct) ? $correct : [$correct] as $c) {
            if (!is_int($c) || !array_key_exists($c, $this->answers)) {
                throw new QuestionException("Invalid correct answer index '$c'.");
            }
        }

        $this->correct = $correct;
    }

    /**
     * Load a list of individual answers. Each has a numeric key and localized text as contents.
     * @param array $answers structure to be loaded
     * @param string $errorPrefix used as a prefix for exception message if something fails
     * @return array of processed and verified answers
     */
    protected function loadAnswers(array $answers, $errorPrefix): array
    {
        try {
            $result = [];
            $schema = self::schemaOfLocaizedText();
            foreach ($answers as $idx => $answer) {
                if (!is_int($idx)) {
                    throw new QuestionException("$errorPrefix, an answer does not have numeric key.");
                }
                $result[$idx] = self::normalize($schema, $answer);
            }
            return $result;
        } catch (Exception $e) {
            throw new QuestionException("$errorPrefix, question answers do not have valid format.", $e);
        }
    }

    /**
     * Helper function for descendant classes that solves instantiation of the answer options.
     * @param mixed $templateJson deserialized template structure
     * @param array $preselect which answers are pre-selected (e.g., to ensure correct answer is always present)
     * @return array of selected answers (with keys, answers without keys are written into $this->answers)
     */
    protected function instantiateAnswers($templateJson, array $preselect = []): array
    {
        $count = $templateJson['count'];
        $allAnswers = $this->loadAnswers($templateJson['answers'], 'Invalid question template');
        $exclusive = $templateJson['exclusive'];

        // verification of correct answer keys
        $correct = is_array($templateJson['correct']) ? $templateJson['correct'] : [ $templateJson['correct'] ];
        foreach ($correct as $key) {
            if (!array_key_exists($key, $allAnswers)) {
                throw new QuestionException("Invalid question template, correct answer refers to non-existing option.");
            }
        }

        try {
            $answers = Random::selectRandomSubset($allAnswers, $count, $preselect, $exclusive);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, model answers are not correctly defined.", $e);
        }

        $this->answers = array_values($answers);
        return $answers; // return it with keys
    }

    /**
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param array $params common parameters for the template
     * @return string raw HTML fragment which is pasted without excaping into the output
     */
    protected function renderChoicesTeplate(Engine $latte, array $params = []): string
    {
        $options = [];
        foreach ($this->answers as $option) {
            $options[] = self::getLocalizedText($option, $params['locale'] ?? 'en');
        }
        $params['options'] = $options;
        return $latte->renderToString(__DIR__ . '/templates/choices.latte', $params);
    }

    /*
     * Implementing the interface
     */

    public function load($json): void
    {
        parent::load($json);

        if (!array_key_exists('answers', $json) || !is_array($json['answers'])) {
            throw new QuestionException("Corrupted question data, propery 'answers' is missing or has invalid value.");
        }

        $this->answers = $this->loadAnswers($json['answers'], 'Corrupted question data');

        if (!array_key_exists('correct', $json) || !$this->isAnswerValid($json['correct'])) {
            throw new QuestionException("Corrupted question data, propery 'correct' is missing or has invalid value.");
        }
        $this->correct = $json['correct'];
    }

    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        $json['answers'] = $this->answers;
        $json['correct'] = $this->correct;
        return $json;
    }

    public function getCorrectAnswer()
    {
        return $this->correct;
    }
}
