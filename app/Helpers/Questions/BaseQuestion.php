<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\Random;
use Latte\Engine;

/**
 * Base question handles localized question texts in the same way for all questions.
 */
abstract class BaseQuestion extends LocalizedEntity implements IQuestion
{
    /*
     * Implementing the interface
     */

    public function instantiate($templateJson, int $seed): void
    {
        $this->loadText($templateJson, 'Invalid question template');
        Random::setSeed($seed);
    }

    public function load($json): void
    {
        $this->loadText($json, 'Corrupted question data');
    }

    public function jsonSerialize(): mixed
    {
        return ['text' => $this->text];
    }

    public function renderCorrectContent(Engine $latte, string $locale): string
    {
        // the default behavior is to use results rendered with the correct answer provided by the question
        return $this->renderResultContent($latte, $locale, $this->getCorrectAnswer(), true);
    }

    public function useRandomSeed(): bool
    {
        return true; // questions are randomized, unless they explicitly override this method
    }
}
