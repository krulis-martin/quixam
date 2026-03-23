<?php

declare(strict_types=1);

namespace Parser;

use RuntimeException;
use Exception;

/**
 * Parser is implemented as an object that may hold possible configuration parameters.
 * (not that we have any now)
 */
class Parser
{
    /**
     * Opening tags for top-level sections
     */
    private const SECTION_TAGS = ['title', 'text', 'correct', 'wrong', 'code', 'item'];

    /**
     * Helper function that gets current line and tries to process it as a tag.
     * @param TextFile $file being read
     * @param $tag output var where tag name is stored
     * @param $closing output flag, true if the tag had closing prefix
     * @param $params output var where remaining arguments of the tag are stored as string
     * @return bool true if the line is a tag
     */
    private function isTag(TextFile $file, &$tag, &$closing, &$params): bool
    {
        $line = $file->current();
        if (preg_match('/^@(?<closing>[\/]?)(?<tag>[a-z]+)\s*(?<params>.*)$/', $line, $matches)) {
            $tag = $matches['tag'];
            $closing = !empty($matches['closing']);
            $params = $matches['params'] ?? '';
            return true;
        }
        return false;
    }

    /**
     * @param TextFile $file (processed lines are consumed)
     * @param LocalizedText $accumulator where the lines are appended
     * @param string $endTag that can close this section explicitly
     * @param array $implicitTerminationTags list of opening tags that implicitly close this section
     * @return bool true if the section was closed explicitly (ending tag, false if it was closed implicitly)
     */
    private function loadText(
        TextFile $file,
        LocalizedText $accumulator,
        string $endTag,
        array $implicitTerminationTags
    ): bool {
        $locale = '';
        $tag = $closing = $params = null;
        while ($file->valid()) {
            if ($this->isTag($file, $tag, $closing, $params)) {
                // process a tag
                if ($closing) {
                    if ($tag === $locale) {
                        $locale = ''; // end of specific locale
                    } elseif ($tag === $endTag) {
                        $file->next(); // consume the ending tag
                        return true; // regular end (by a valid closing tag)
                    } else {
                        throw new RuntimeException("Unexpected closing tag @/$tag in localized text section.");
                    }
                } else {
                    if (in_array($tag, $implicitTerminationTags)) {
                        return false; // implicit end of localized text section (valid opening tag of next section)
                    } elseif (in_array($tag, LocalizedText::LOCALES)) {
                        $locale = $tag; // start of specific locale section
                    } else {
                        throw new RuntimeException("Unexpected tag @$tag in localized text section.");
                    }
                }
            } else {
                // no tag -> add text to accumulator
                $accumulator->append($locale, $file->current());
            }
            $file->next();
        }
        return false;
    }

    /*
     * Tag-name invoked methods
     */

    private function processTagQuestion(TextFile $file, Question $question, $params): void
    {
        $paramTokens = preg_split('/\s+/', trim($params));
        $type = array_shift($paramTokens);
        $question->setType($type, $paramTokens);
        $file->next();
    }

    private function processTagCorrect(TextFile $file, Question $question, $params): void
    {
        $file->next(); // consume the opening tag

        $correct = new LocalizedText();
        if (
            !$this->loadText($file, $correct, 'correct', ['else', ...self::SECTION_TAGS]) // not closed explicitly
            && $this->isTag($file, $tag, $closing, $params) // followed by a tag
            && !$closing
            && $tag === 'else' // it's a exclusive branch!
        ) {
            // load also the second branch
            $wrong = new LocalizedText();
            $file->next();
            $this->loadText($file, $wrong, 'wrong', self::SECTION_TAGS);
            $question->appendCorrectWrong($correct, $wrong);
        } else {
            $question->appendCorrect($correct);
        }
    }

    private function processTagWrong(TextFile $file, Question $question, $params)
    {
        $file->next(); // consume the opening tag

        $answer = new LocalizedText();
        $this->loadText($file, $answer, 'wrong', self::SECTION_TAGS);
        $question->appendWrong($answer);
    }

    private function processTagCode(TextFile $file, Question $question, $params): void
    {
        $file->next(); // consume the opening tag

        $closingTags = self::SECTION_TAGS;
        $closingTags[] = '/code';
        while ($file->valid()) {
            if ($this->isTag($file, $tag, $closing, $params)) {
                $tag = $closing ? "/$tag" : $tag;
                if (in_array($tag, $closingTags)) {
                    if ($closing) {
                        $file->next(); // consume the closing tag (opening tags must not be consumed)
                    }
                    break; // the while and end
                } else {
                    throw new RuntimeException("Unexpected tag @$tag in plain section.");
                }
            } else {
                // no tag -> add text to accumulator
                $question->appendCode($file->current());
            }
            $file->next();
        }
    }

    private function processTagItem(TextFile $file, Question $question, $params)
    {
        $paramTokens = preg_split('/\s+/', trim($params));
        $correctOrder = null;
        $mandatory = false;
        $preselected = false;
        $group = null;
        if (count($paramTokens) > 0 && is_numeric($paramTokens[0])) {
            $correctOrder = (int)$paramTokens[0];
        }
        if (count($paramTokens) > 1 && $paramTokens[1]) {
            if (preg_match('/^([+!]|[+]!|![+])$/', $paramTokens[1])) {
                // flags
                $mandatory = strpos($paramTokens[1], '!') !== false;
                $preselected = strpos($paramTokens[1], '+') !== false;
                if (count($paramTokens) > 2 && $paramTokens[2]) {
                    $group = $paramTokens[2];
                }
            } else {
                $group = $paramTokens[1];
            }
        }

        $file->next(); // consume the opening tag
        $text = new LocalizedText();
        $this->loadText($file, $text, 'item', self::SECTION_TAGS);
        $question->appendItem($text, $correctOrder, $mandatory, $preselected, $group);
    }

    /**
     * Parse a file and fill in a new question object.
     * @param TextFile $file to be parsed
     * @return Question|null parsed data
     */
    public function parseFile(TextFile $file): ?Question
    {
        try {
            $question = new Question();
            $file->rewind();
            while ($file->valid()) {
                if ($this->isTag($file, $tag, $closing, $params)) {
                    if ($closing) {
                        throw new RuntimeException("Unexpected closing tag @/$tag found.");
                    }

                    $method = "processTag" . ucfirst($tag);
                    if (method_exists($this, $method)) {
                        $this->$method($file, $question, $params);
                    } elseif (in_array($tag, ['title', 'text'])) {
                        $file->next();
                        $this->loadText($file, $question->$tag, $tag, self::SECTION_TAGS);
                    } else {
                        throw new RuntimeException("Unknown tag @$tag.");
                    }
                } else {
                    $file->next();
                }
            }

            return $question;
        } catch (Exception $e) {
            echo "Fatal error: ", $e->getMessage(), "\n";
            $file->dumpReadingPosition();
            return null;
        }
    }
}
