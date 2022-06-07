<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Represents a question template that is govern by user code.
 * The user code is executed in the context of this class and prepare question (with data) of regular kind.
 */
class DynamicQuestion extends DynamicQuestionBase
{
    protected function generateInternal(): void
    {
        // !!! DANGER - eval() is used here !!!
        if (eval($this->getCode()) === false) {
            throw new QuestionException("Generator code execution failed.");
        }
    }
}
