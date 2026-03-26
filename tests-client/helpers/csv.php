<?php

declare(strict_types=1);

namespace Helpers;

use RuntimeException;
use InvalidArgumentException;
use Throwable;
use Iterator;

final class Csv implements Iterator
{
    private $descriptor;
    private $requiredColumns;
    private $header = null;
    private $rows = [];

    /**
     * Constructor is given validation parameters.
     * @param array $descriptor column descriptor, which is an associative array of column name => type;
     *                          type is either a class name, string with base type name, or a callable that
     *                          casts a loaded value into proper type (possibly with validation)
     * @param array|bool $requiredColumns list of required column names (or true to require all columns in descriptor)
     */
    public function __construct(array $descriptor, mixed $requiredColumns = [])
    {
        $this->descriptor = $descriptor;
        foreach ($descriptor as $name => $type) {
            if (!is_string($name) && !(is_string($type) || is_callable($type))) {
                throw new InvalidArgumentException(
                    "CSV descriptor must be an associative array of column name => type."
                );
            }
        }

        $this->requiredColumns = is_array($requiredColumns) ? $requiredColumns : array_keys($descriptor);
    }

    private function castCell(string $value, string|callable $type, int $lineNumber): mixed
    {
        if (is_callable($type)) {
            try {
                return $type($value);
            } catch (Throwable $e) {
                throw new RuntimeException("Error casting value '$value' on line $lineNumber: " . $e->getMessage());
            }
        } elseif (class_exists($type)) {
            try {
                return new $type($value);
            } catch (Throwable $e) {
                throw new RuntimeException("Error creating instance of '$type' from '$value' on line $lineNumber: "
                    . $e->getMessage());
            }
        } else {
            if (!settype($value, $type)) {
                throw new RuntimeException("Error casting value '$value' to type '$type' on line $lineNumber.");
            }
            return $value;
        }
    }
    /**
     * Load and validate a CSV file according to the given descriptor and required columns.
     */
    public function load(string $csvFile, string $delimiter = ','): void
    {
        if (!file_exists($csvFile)) {
            throw new RuntimeException("CSV file '$csvFile' does not exist.");
        }

        $fp = fopen($csvFile, 'r');
        if ($fp === false) {
            throw new RuntimeException("Cannot open CSV file '$csvFile' for reading.");
        }

        $rows = [];
        if (($header = fgetcsv($fp, 0, $delimiter)) !== false) {
            foreach ($header as $idx => &$name) {
                $name = trim($name);
                if (!$name) {
                    throw new RuntimeException("Column [$idx] in CSV file '$csvFile' has an empty name.");
                }
            }
            foreach ($this->requiredColumns as $col) {
                if (!in_array($col, $header)) {
                    throw new RuntimeException("CSV file '$csvFile' is missing required column '$col'.");
                }
            }
        }

        $lineNumber = 1;
        while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
            ++$lineNumber;
            $data = array_pad($data, count($header), null);
            array_splice($data, count($header));
            $row = array_combine($header, $data);
            foreach ($this->descriptor as $name => $type) {
                if (array_key_exists($name, $row)) {
                    $row[$name] = $this->castCell($row[$name], $type, $lineNumber);
                }
            }
            $rows[] = (object)$row;
        }

        fclose($fp);

        $this->header = $header;
        $this->rows = $rows;
    }

    public function getHeader(): ?array
    {
        return $this->header;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /*
     * Iterator implementation
     */
    public function current(): mixed
    {
        return current($this->rows);
    }

    public function key(): mixed
    {
        return key($this->rows);
    }

    public function next(): void
    {
        next($this->rows);
    }

    public function rewind(): void
    {
        reset($this->rows);
    }

    public function valid(): bool
    {
        return $this->current() !== false;
    }
}
