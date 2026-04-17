<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\Answer;
use App\Model\Entity\Question;
use Latte\Extension;

class FunctionsExtension extends Extension
{
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

    public function hasAnswerFullPoints(?Answer $answer): bool
    {
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() >= $answer->getQuestion()->awardPointsForAnswer(0);
    }

    public function hasAnswerPartialPoints(?Answer $answer): bool
    {
        $question = $answer?->getQuestion();
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() > $question->awardPointsForAnswer($question->getItemsCount())
            && $answer->getPoints() < $question->awardPointsForAnswer(0);
    }

    public function hasAnswerNoPoints(?Answer $answer): bool
    {
        $question = $answer?->getQuestion();
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() <= $question->awardPointsForAnswer($question->getItemsCount());
    }

    public function answerStyle(?Answer $answer, bool $testFinished = false): ?string
    {
        if (!$answer) {
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
