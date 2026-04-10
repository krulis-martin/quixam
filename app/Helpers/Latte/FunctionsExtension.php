<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\Answer;
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
        ];
    }

    public function hasAnswerFullPoints(?Answer $answer): bool
    {
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() >= $answer->getQuestion()->getPoints();
    }

    public function hasAnswerPartialPoints(?Answer $answer): bool
    {
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() > 0 && $answer->getPoints() < $answer->getQuestion()->getPoints();
    }

    public function hasAnswerNoPoints(?Answer $answer): bool
    {
        return $answer !== null && $answer->getPoints() !== null
            && $answer->getPoints() <= 0;
    }

    public function answerStyle(?Answer $answer, bool $testFinished = false): ?string
    {
        if (!$answer) {
            return null;
        }

        if (!$testFinished) {
            return 'success';
        } elseif ($answer->getPoints() === null) {
            return 'secondary';
        } elseif ($answer->getPoints() >= $answer->getQuestion()->getPoints()) {
            return 'success';
        } elseif ($answer->getPoints() > 0) {
            return 'warning';
        } elseif ($answer->getPoints() <= 0) {
            return 'danger';
        }

        return null;
    }
}
