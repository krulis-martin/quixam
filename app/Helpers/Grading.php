<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\SmartObject;
use JsonSerializable;
use Exception;
use Iterator;

/**
 * Keeps grading limits and can compute a grade for given amount of points.
 */
class Grading implements JsonSerializable, Iterator
{
    use SmartObject;

    private const BEST_COLOR = '#198754';   // bootstap success
    private const MEDIUM_COLOR = '#ffc107'; // bootstrap warning
    private const WORST_COLOR = '#6c757d';  // bootstrap secondary
    private const FAIL_COLOR = '#dc3545';   // bootstrap danger

    /**
     * Return a color formatted in CSS hexa RGB (6 chars - RRGGBB) with # prefix.
     * @param array $color (as 3-component tupple r,g,b in ints 0-255)
     * @return string '#rrggbb' in hex
     */
    private static function cssColorFormat(array $color): string
    {
        $hexColor = array_map(function (int $c): string {
            return str_pad(dechex($c), 2, "0", STR_PAD_LEFT);
        }, $color);
        return '#' . join('', $hexColor);
    }

    /**
     * @var array mark => points lower bound
     */
    private $grading;

    /** @var array holding keys of grading (marks) + used for Iterator interface */
    private $marks;

    /**
     * @var array mark => CSS color string
     * This may be replaced in the future with some sort of interpolation.
     */
    private $colors = [
        1 => self::BEST_COLOR,
        2 => self::MEDIUM_COLOR,
        3 => self::WORST_COLOR,
        4 => self::FAIL_COLOR,
    ];

    /**
     * Initialize grading with marks and limits.
     * @param array $grading
     */
    public function __construct(array $grading = [])
    {
        foreach ($grading as &$limit) {
            if (!is_numeric($limit)) {
                throw new Exception("Grading limits must be numeric values only.");
            }
            $limit = (int)$limit;
        }
        arsort($grading, SORT_NUMERIC); // associative, reverse
        $this->grading = $grading;

        $this->marks = array_keys($this->grading);
        reset($this->marks);
    }

    /**
     * Compute grade for given amount of points
     * @param int $points scored to a student
     * @return int|string|null a mark or null, if the mark is not specified for given amount of points
     */
    public function getGrade(int $points)
    {
        foreach ($this->grading as $grade => $limit) {
            if ($points >= $limit) {
                return $grade;
            }
        }
        return null;
    }

    /**
     * Return the most appropriate color for a mark (computed from given amount of points).
     * @param int $points scored to a student
     * @return string a color in CSS format (applicable in stylesheets)
     */
    public function getGradeColor(int $points): string
    {
        $grade = $this->getGrade($points);
        if ($grade !== null && array_key_exists($grade, $this->colors)) {
            return $this->colors[$grade];
        }
        return self::FAIL_COLOR;
    }

    /**
     * Return point range for given mark.
     * @param int|string $mark
     * @return array [ min, max ], null is returned as max for the best mark
     */
    public function getMarkRange($mark): array
    {
        $upper = null;
        foreach ($this->grading as $m => $limit) {
            if ($m === $mark) {
                return [$limit, $upper];
            }
            $upper = $limit - 1;
        }
        return $upper > 0 ? [0, $upper] : [];
    }

    /*
     * Interface implementations
     */

    public function jsonSerialize(): mixed
    {
        return $this->grading;
    }

    public function current(): mixed
    {
        return current($this->marks);
    }

    public function key(): mixed
    {
        return key($this->marks);
    }

    public function next(): void
    {
        next($this->marks);
    }

    public function rewind(): void
    {
        reset($this->marks);
    }

    public function valid(): bool
    {
        return key($this->marks) !== null;
    }
}
