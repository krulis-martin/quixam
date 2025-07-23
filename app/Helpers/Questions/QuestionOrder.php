<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\QuestionException;
use App\Helpers\Random;
use Nette\Schema\Expect;
use Latte\Engine;
use Exception;

/**
 * Question that expects one or multiple numbers as an answer.
 */
final class QuestionOrder extends BaseQuestion
{
    /** @var int|null */
    private $minCount = null;

    /** @var int|null */
    private $maxCount = null;

    /** @var QuestionOrderItem[] */
    protected $items = [];

    /**
     * Assembles nette validation schema for question template data.
     */
    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocalizedText()->required(),
            'minCount' => Expect::int(),
            'maxCount' => Expect::int(),
            'items' => Expect::listOf(QuestionOrderItem::schemaOfTemplate())->required(),
        ])->skipDefaults()->castTo('array');
    }

    /**
     * Configure min-max range for accepted answers (how many items are expected).
     * @param int $min
     * @param int $max
     */
    public function setLimits(int $min, int $max): void
    {
        if ($min > $max || $min < 0) {
            throw new QuestionException("Invalid count range [$min, $max].");
        }
        $this->minCount = $min;
        $this->maxCount = $max;
    }

    /**
     * Add another item into the fold.
     * @param string|array $text
     * @param int|null $correctOrder used to determine whether the items are sorted properly
     *                               (null = item is not in the correct answer)
     * @param bool $mandatory whether this item must be always used in instantiation
     * @param bool $preselected if true, the item is preselected in the answer (in UI)
     * @param string $group identifier (all items in a group must be either used or not used in instantiation),
     *                      null = no particular group used
     */
    public function addItem(
        $text,
        ?int $correctOrder,
        bool $mandatory = false,
        bool $preselected = false,
        string $group = null
    ): QuestionOrderItem {
        $item = new QuestionOrderItem($text, $correctOrder, $mandatory, $preselected, $group);
        $this->items[] = $item;
        return $item;
    }

    /*
     * Implementing the interface
     */

    /**
     * Helper function that loads internal parameters from JSON (template or save question data).
     * @param array $json decoded input data structure
     */
    private function loadParameters(array $json): void
    {
        foreach (['minCount', 'maxCount'] as $key) {
            if (array_key_exists($key, $json)) {
                $this->$key = $json[$key];
            }
        }
        foreach ($json['items'] as $key => $jsonItem) {
            $item = new QuestionOrderItem();
            $item->load($jsonItem);
            $this->items[$key] = $item;
        }
    }

    /**
     * Filter items and remove all that are not mandatory (or within a mandatory group).
     * @return QuestionOrderItem[] removed items
     */
    private function removeOptionalItems(): array
    {
        $mandatoryGroups = [];
        foreach ($this->items as $item) {
            if ($item->getGroup() && $item->isMandatory()) {
                $mandatoryGroups[$item->getGroup()] = true;
            }
        }

        // split out removed items
        $items = [];
        $removed = [];
        foreach ($this->items as $item) {
            if ($item->isMandatory() || ($item->getGroup() && !empty($mandatoryGroups[$item->getGroup()]))) {
                $items[] = $item;
            } else {
                $removed[] = $item;
            }
        }

        $this->items = $items;
        return $removed;
    }

    /**
     * Helper function that splits items into groups (free items are pleace in single-item groups).
     * @return array[] array of groups, each group is an array of items
     */
    private static function splitInGroups(array $items): array
    {
        $groups = [];
        $namedGroups = [];
        foreach ($items as $item) {
            if ($item->getGroup()) {
                $namedGroups[$item->getGroup()] = $namedGroups[$item->getGroup()] ?? [];
                $namedGroups[$item->getGroup()][] = $item;
            } else {
                $groups[] = [$item];
            }
        }

        return array_merge($groups, array_values($namedGroups));
    }

    public function instantiate($templateJson, int $seed): void
    {
        try {
            $templateJson = self::normalize(self::schemaOfTemplate(), $templateJson);
        } catch (Exception $e) {
            throw new QuestionException("Invalid question template, the data do not have a valid structure.", $e);
        }

        parent::instantiate($templateJson, $seed);
        $this->loadParameters($templateJson);

        // sanity checks
        if (!is_int($this->minCount) || !is_int($this->maxCount)) {
            throw new QuestionException(
                "Invalid question template, the minimal and maximal counts must be properly set."
            );
        }
        if ($this->minCount < 0) {
            throw new QuestionException("Invalid question template, the minimal count limit must not be negative.");
        }
        if ($this->minCount > $this->maxCount) {
            throw new QuestionException("Invalid question template, the minimal-maximal count range is inverted.");
        }
        if ($this->minCount > count($this->items) || $this->maxCount > count($this->items)) {
            throw new QuestionException(
                "Invalid question template, the number of items is out of the minCount-maxCount range."
            );
        }

        // select items for the instance
        $candidates = $this->removeOptionalItems();
        $min = max($this->minCount - count($this->items), 0);
        $max = $this->maxCount - count($this->items);
        if ($max > 0) {
            // prepare groups (each group is either entirely added or ignored)
            $groups = self::splitInGroups($candidates);
            $sizes = [];
            foreach ($groups as $idx => $group) {
                $sizes[$idx] = count($group);
            }

            // add items from selected groups into items of the question
            $selectedGroups = Random::selectRandomKnapsack($sizes, $min, $max);
            foreach ($selectedGroups as $idx) {
                foreach ($groups[$idx] as $item) {
                    $this->items[] = $item;
                }
            }
        }

        Random::shuffleArray($this->items);
    }

    public function load($json): void
    {
        try {
            $json = self::normalize(self::schemaOfTemplate(), $json);
        } catch (Exception $e) {
            throw new QuestionException("The question data do not have a valid structure.", $e);
        }

        parent::load($json);
        $this->loadParameters($json);
    }

    private function renderOrderTemplate(Engine $latte, string $locale, $answer, array $params = []): string
    {
        $params['readonly'] = $params['readonly'] ?? false;
        $params['correctClass'] = $params['correctClass'] ?? '';
        $params['locale'] = $locale;

        // prepare items
        $params['items'] = $this->items;
        $params['selected'] = $answer ? $answer : [];
        if ($answer === null) { // if no answer is given, preselect items for a new one
            foreach ($this->items as $idx => $item) {
                if ($item->isPreselected()) {
                    $params['selected'][] = $idx;
                }
            }
        }

        $params['remaining'] = $this->items; // not selected items
        foreach ($params['selected'] as $idx) {
            unset($params['remaining'][$idx]);
        }
        $params['remaining'] = array_keys($params['remaining']);

        return $latte->renderToString(__DIR__ . '/templates/order.latte', $params);
    }

    public function renderFormContent(Engine $latte, string $locale, $answer = null): string
    {
        return $this->renderOrderTemplate($latte, $locale, $answer);
    }


    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?bool $answerIsCorrect = null
    ): string {
        $params = ['readonly' => true];
        if ($answerIsCorrect !== null) {
            $params['correctClass'] = $answerIsCorrect ? 'correct' : 'wrong';
        }
        return $this->renderOrderTemplate($latte, $locale, $answer, $params);
    }

    public function processAnswerSubmit(array $postData)
    {
        if (!array_key_exists('answer', $postData) || !trim($postData['answer'])) {
            return [];
        }

        // the data are encoded in text input as a space-separated sequence of indices
        $answer = preg_split('/\s+/', trim($postData['answer']));
        foreach ($answer as &$idx) {
            if (!is_numeric($idx)) {
                return null;
            }
            $idx = (int)$idx;
        }

        return $answer;
    }

    public function isAnswerValid($answer): bool
    {
        if (!is_array($answer)) {
            return false;
        }

        foreach ($answer as $key) {
            if (!is_int($key) || !array_key_exists($key, $this->items)) {
                return false;
            }
        }

        return true;
    }

    public function isAnswerCorrect($answer): bool
    {
        if (!$this->isAnswerValid($answer)) {
            return false;
        }

        $items = $this->items;
        $selected = [];
        foreach ($answer as $key) {
            $selected[] = $items[$key];
            unset($items[$key]);
        }

        foreach ($items as $item) {
            if ($item->isCorrect()) {
                return false; // one of the correct items was not selected
            }
        }

        $lastOrder = PHP_INT_MIN;
        foreach ($selected as $item) {
            if (!$item->isCorrect() || $item->getCorrectOrder() < $lastOrder) {
                return false;
            }
            $lastOrder = $item->getCorrectOrder();
        }

        return true;
    }

    public function getCorrectAnswer()
    {
        $items = [];
        foreach ($this->items as $idx => $item) {
            if ($item->isCorrect()) {
                $items[$idx] = $item->getCorrectOrder();
            }
        }
        asort($items, SORT_NUMERIC);
        return array_keys($items);
    }

    public function jsonSerialize(): mixed
    {
        $json = parent::jsonSerialize();
        $json['items'] = $this->items;
        return $json;
    }
}
