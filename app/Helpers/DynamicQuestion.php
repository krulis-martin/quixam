<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Represents a question template that is govern by user code.
 * The user code is executed in the context of this class and prepare question (with data) of regular kind.
 */
class DynamicQuestion extends DynamicQuestionBase
{
    /**
     * The main routine, which generate the dynamic question.
     * This method is intentionally in a derived class so private things are kept in the base class.
     */
    public function generate(): void
    {
        exec($this->getCode());

        if (!$this->getType() || !$this->getQuestion()) {
            throw new QuestionException("The generator did not initialize the question.");
        }

        // the text must be copied from internal buffer to the constructed question at the end
        $question = $this->getQuestion();
        if ($question instanceof \App\Helpers\Questions\BaseQuestion) {
            $question->setText($this->getText());
        }
    }
}
