<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\QuestionException;
use App\Helpers\Random;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\SmartObject;
use Exception;

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
        return [ 'text' => $this->text ];
    }
}
