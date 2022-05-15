<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\SmartObject;

class QuestionFactory
{
    use SmartObject;

    /** @var array */
    private $knownQuestionTypes;

    public function __construct($knownQuestionTypes = [])
    {
        $this->knownQuestionTypes = $knownQuestionTypes;
        foreach ($this->knownQuestionTypes as $type => $qt) {
            if (!in_array('App\Helpers\IQuestion', class_implements($qt))) {
                throw new QuestionException("Configuration error. Registered question type '"
                    . $type . "' is not represented by a regular implementation of IQuestion interface.");
            }
        }
    }

    public function create(string $type): IQuestion
    {
        if (!array_key_exists($type, $this->knownQuestionTypes)) {
            throw new QuestionException("Unknown question type '$type'.");
        }
        return new $this->knownQuestionTypes[$type]();
    }
}
