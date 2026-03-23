<?php

declare(strict_types=1);

namespace Parser;

use RuntimeException;

/**
 * Represents raw text file as a sequence of lines.
 * Handles macro-preprocessor stuff (includes and snippets).
 */
class TextFile extends TextBlock
{
    /**
     * Helper used for loading (to detect cyclic dependencies).
     */
    private $cache = [];

    /**
     * @var string Real canonized path to this file.
     */
    private $path;

    /**
     * List of declared snippets. Actually this is relevant only for the root file.
     */
    private $snippets = [];

    private function __construct(string $path, array $data)
    {
        parent::__construct($data);
        $this->path = $path;
    }

    /**
     * Internal helper function that loads a snippet declaration.
     * @param TextFile $file from which the snippet is loaded
     * @param int $idx reference to line index (which is advanced as lines get consumed)
     * @param string $id name of the snippet
     * @param array $snippets map of already declared snippets (for possible de-references)
     * @return TextSnippet
     */
    private static function loadSnippet(TextFile $file, int &$idx, string $id, array $snippets): TextSnippet
    {
        $offset = $idx;
        $data = [];
        while ($idx < count($file->data)) {
            // get a copy of the line and erase it from the file
            $line = $file->data[$idx];
            $file->data[$idx++] = null;

            if (preg_match('#^@/snippet#', $line)) {
                $line = null;
                break;
            }

            if (preg_match('/^@include\s+/', $line)) {
                // includes in snippets are forbidden
                throw new RuntimeException("An @include found inside a snippet $id (" . $file->getPath() . ":$idx)");
            } elseif (preg_match('/^@paste\s+(?<id>\S+)$/', $line, $matches)) {
                // paste snippet
                $id = $matches['id'];
                if (!array_key_exists($id, $snippets)) {
                    throw new RuntimeException("Snippet '$id' does not exist (" . $file->getPath() . ":$idx)");
                }
                $line = $snippets[$id];
            } elseif (preg_match('/^@snippet\s+$/', $line)) {
                // nested declarations of snippets are forbidden
                throw new RuntimeException("A @snippet declaration found inside a snippet $id ("
                    . $file->getPath() . ":$idx)");
            }

            $data[] = $line;
        }

        return new TextSnippet($data, $file->getPath(), $id, $offset);
    }

    /**
     * Loads data from given file, resolves includes, and return instance of a raw file.
     * Uses internal cache to avoid repetitive loading (and to detect loop dependencies).
     * @param string $origPath of the file to be loaded
     * @param TextFile|null reference to a root file (if nested include is being loaded)
     * @return TextFile
     */
    public static function load(string $origPath, ?TextFile $rootFile = null): TextFile
    {
        $path = realpath($origPath);
        if (!$path || !file_exists($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException("File '$origPath' does not exist.");
        }

        if ($rootFile && array_key_exists($path, $rootFile->cache)) {
            if ($rootFile->cache[$path] === null) {
                throw new RuntimeException("Cyclic dependency found in @include directives.");
            }
            return $rootFile->cache[$path];
        }

        // the actual load
        $data = file($path, FILE_IGNORE_NEW_LINES);
        if ($data === false) {
            throw new RuntimeException("Unable to load data from file '$path'.");
        }

        // satisfy includes
        $file = new self($path, $data);
        if (!$rootFile) {
            $rootFile = $file;
        }
        $rootFile->cache[$path] = null;

        // Iterate over the lines and post-process them for includes and snippets.
        $idx = 0;
        $comment = false;
        while ($idx < count($file->data)) {
            $line = &$file->data[$idx++];

            if ($comment || preg_match('/^@comment/', $line)) {
                // comment is running or starting
                $comment = !preg_match('#^@/comment#', $line);
                $line = null; // skip all the lines in the comment
                continue;
            }

            if (preg_match('/^@include\s+(?<path>\S+)$/', $line, $matches)) {
                // include a file
                $line = self::load(dirname($path) . '/' . $matches['path'], $rootFile);
            } elseif (preg_match('/^@paste\s+(?<id>\S+)$/', $line, $matches)) {
                // paste a snippet
                $id = $matches['id'];
                if (!array_key_exists($id, $rootFile->snippets)) {
                    throw new RuntimeException("Snippet '$id' does not exist ($path:$idx)");
                }
                $line = $rootFile->snippets[$id];
            } elseif (preg_match('/^@snippet\s+(?<id>\S+)$/', $line, $matches)) {
                // load a declaration of a snippet
                $id = $matches['id'];
                if (array_key_exists($id, $rootFile->snippets)) {
                    echo "Warning: snippet '$id' is being redeclared ($path:$idx)\n";
                }
                $rootFile->snippets[$id] = self::loadSnippet($file, $idx, $id, $rootFile->snippets);
                $line = null;
            }
        }

        $rootFile->cache[$path] = $file;
        return $file;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Dumps current reading location. Helpful if error should be printed.
     */
    public function dumpReadingPosition(): void
    {
        $prefix = "at ";
        foreach ($this->readingStack as $i => $textBlock) {
            if ($textBlock instanceof TextFile or $textBlock instanceof TextSnippet) {
                echo $prefix;
                $prefix = "... included from ";

                if ($textBlock instanceof TextSnippet) {
                    echo "snippet [", $textBlock->getId(), "] in ";
                }

                $line = $textBlock->getCurrentLine() + ($i == 0 ? 1 : 0); // +1 must be added to the first item
                echo $textBlock->getPath(), ' (line ', $line, ")\n";
            }
        }
    }
}
