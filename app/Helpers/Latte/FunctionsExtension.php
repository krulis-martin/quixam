<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\Answer;
use App\Model\Entity\Question;
use Latte\Extension;

/**
 * Latte extension for functions related to displaying the test results.
 */
class FunctionsExtension extends Extension
{
    /**
     * Register functions for Latte templates.
     * @return array function name => callback
     */
    public function getFunctions(): array
    {
        // Register methods as Latte functions
        return [
            'hasAnswerFullPoints' => $this->hasAnswerFullPoints(...),
            'hasAnswerPartialPoints' => $this->hasAnswerPartialPoints(...),
            'hasAnswerNoPoints' => $this->hasAnswerNoPoints(...),
            'answerStyle' => $this->answerStyle(...),
            'formatPoints' => $this->formatPoints(...)
        ];
    }

    /**
     * Check if the answer has full points (i.e., no mistakes).
     * @param Answer|null $answer
     * @return bool true if the answer has full points, false otherwise
     */
    public function hasAnswerFullPoints(?Answer $answer): bool
    {
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() >= $answer->getQuestion()->awardPointsForAnswer(0);
    }

    /**
     * Check if the answer has partial points (i.e., some mistakes but not all wrong nor correct).
     * @param Answer|null $answer
     * @return bool true if the answer has only partial points, false otherwise
     */
    public function hasAnswerPartialPoints(?Answer $answer): bool
    {
        $question = $answer?->getQuestion();
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() > $question->awardPointsForAnswer($question->getItemsCount())
            && $answer->getPoints() < $question->awardPointsForAnswer(0);
    }

    /**
     * Check if the answer has no points (i.e., all mistakes or empty answer).
     * @param Answer|null $answer
     * @return bool true if the answer has no points, false otherwise
     */
    public function hasAnswerNoPoints(?Answer $answer): bool
    {
        $question = $answer?->getQuestion();
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() <= $question->awardPointsForAnswer($question->getItemsCount());
    }

    /**
     * Determine the CSS class-base for styling (in Bootstrap) based on the points of the answer.
     * @param Answer|null $answer the answer for which the style should be determined
     * @param bool $testFinished whether the test is finished (grading is displayed)
     * @return string|null CSS class base for styling (e.g., 'success', 'warning', 'danger'),
     *                     null if no special styling should be applied
     */
    public function answerStyle(?Answer $answer, bool $testFinished = false): ?string
    {
        if (!$answer || (!$testFinished && $answer->getAnswer() === null)) {
            return null;
        }

        $question = $answer->getQuestion();
        $minPoints = $question->awardPointsForAnswer($question->getItemsCount()); // max mistakes
        $maxPoints = $question->awardPointsForAnswer(0); // no mistakes

        if (!$testFinished) {
            return 'success';
        } elseif ($answer->getPoints() === null) {
            return 'secondary';
        } elseif ($answer->getPoints() >= $maxPoints) {
            return 'success';
        } elseif ($answer->getPoints() <= $minPoints) {
            return 'danger';
        } else {
            return 'warning';
        }
    }

    /**
     * Format points for display, showing a range if there are different points for different numbers of mistakes.
     * @param Question|null $question the question for which the points should be formatted
     * @return string|null formatted points for display, null if the question is null
     */
    public function formatPoints(?Question $question): ?string
    {
        if (!$question) {
            return null;
        }

        $minPoints = $question->awardPointsForAnswer($question->getItemsCount()); // max mistakes
        $maxPoints = $question->awardPointsForAnswer(0); // no mistakes

        if ($minPoints === 0) {
            return (string)$maxPoints;
        } elseif ($maxPoints === 0) {
            return (string)$minPoints;
        } else {
            return "[$minPoints..$maxPoints]";
        }
    }
}
