<?php

declare(strict_types=1);

namespace Parser;

use Iterator;

/**
 * A block of text represented as array of lines.
 * Each line must be either a string, or a reference to nested text block.
 * The iterator can smoothly iterate over the text blocks.
 */
class TextBlock implements Iterator
{
    protected $data;
    protected $readingStack = [];

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->rewind();
    }

    /**
     * Internal stack consolidation that needs to be performed every time current position is advanced or reset.
     */
    private function consolidateStack()
    {
        // remove nested blocks that reached the end
        while ($this->readingStack && key($this->readingStack[0]->data) === null) {
            array_shift($this->readingStack);
        }

        // if the current line holds nested block reference, enter inside
        while ($this->readingStack && current($this->readingStack[0]->data) instanceof TextBlock) {
            $nestedBlock = current($this->readingStack[0]->data);
            reset($nestedBlock->data);
            next($this->readingStack[0]->data);
            array_unshift($this->readingStack, $nestedBlock);
        }

        if ($this->valid() && $this->current() === null) {
            $this->next(); // skip nullified lines
        }
    }

    public function getCurrentLine()
    {
        // line number refers to current line key (or the total number of lines if we have reached the end)
        return key($this->data) ?? count($this->data);
    }

    /*
     * Iterator implementation
     */

    public function current(): mixed
    {
        return $this->readingStack ? current($this->readingStack[0]->data) : null;
    }

    public function key(): mixed
    {
        $key = [];
        foreach ($this->readingStack as $textBlock) {
            array_unshift($key, key($textBlock->data));
        }
        return $key ? join('-', $key) : null;
    }

    public function next(): void
    {
        if ($this->readingStack) {
            next($this->readingStack[0]->data);
            $this->consolidateStack();
        }
    }

    public function rewind(): void
    {
        $this->readingStack = [$this];
        reset($this->data);
        $this->consolidateStack();
    }

    public function valid(): bool
    {
        return (bool)$this->readingStack;
    }
}
