<?php

declare(strict_types=1);

namespace Parser;

/**
 * Represents a snippet block (a macro, that is pasted in various places using @paste).
 */
class TextSnippet extends TextBlock
{
    private $id; // name of the snippet
    private $path; // of the file where the snippet was declared
    private $offset; // of the first line of the snippet declaration within the file

    public function __construct(array $data, string $id, string $path, int $offset)
    {
        parent::__construct($data);
        $this->id = $id;
        $this->path = $path;
        $this->offset = $offset;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCurrentLine(): int // override
    {
        return parent::getCurrentLine() + $this->offset;
    }
}
