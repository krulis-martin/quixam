<?php

declare(strict_types=1);

namespace Parser;

/**
 * This is a structure that holds all question data.
 */
class ParsedQuestion
{
    private $type = null;
    private $parameters = [];
    private $title;
    private $text;
    private $answers = [];
    private $correct = []; // indices to answers
    private $exclusiveAnswers = []; // [ [ idx1, idx2 ] ]
    private $code = [];
    private $items = [];

    public function __construct()
    {
        $this->title = new LocalizedText();
        $this->text = new LocalizedText();
    }

    public function __get(string $name)
    {
        return property_exists($this, $name) ? $this->$name : null;
    }

    public function __isset(string $name)
    {
        return property_exists($this, $name);
    }

    public function setType(string $type, array $parameters)
    {
        $this->type = $type;
        $this->parameters = $parameters;
    }

    /**
     * Append a correct answer.
     */
    public function appendCorrect(LocalizedText $answer): void
    {
        $this->correct[] = count($this->answers);
        $this->answers[] = $answer;
    }

    /**
     * Append a wrong answer.
     */
    public function appendWrong(LocalizedText $answer): void
    {
        $this->answers[] = $answer;
    }

    /**
     * Append mutually exclusive correct-wrong pair of answers.
     */
    public function appendCorrectWrong(LocalizedText $correct, LocalizedText $wrong): void
    {
        $idx = count($this->answers);
        $this->exclusiveAnswers[] = [$idx, $idx + 1];
        $this->appendCorrect($correct);
        $this->appendWrong($wrong);
    }

    public function appendItem(
        LocalizedText $text,
        ?int $correctOrder,
        bool $mandatory,
        bool $preselected,
        ?string $group
    ): void {
        $this->items[] = [
            'text' => $text,
            'correctOrder' => $correctOrder,
            'mandatory' => $mandatory,
            'preselected' => $preselected,
            'group' => $group,
        ];
    }

    public function appendCode(string $line): void
    {
        $this->code[] = $line;
    }

    public function getCode(): string
    {
        return join("\n", $this->code);
    }
}
