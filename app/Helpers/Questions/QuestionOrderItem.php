<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use Nette\Schema\Expect;
use Nette\SmartObject;
use Exception;
use JsonSerializable;

/**
 *
 */
final class QuestionOrderItem extends LocalizedEntity implements JsonSerializable
{
    use SmartObject;

    /** @var int|null used to verify ordering, null if should not be selected */
    private $correctOrder = null;

    /** @var bool if true, the item is always added to pool when question is instantiated */
    private $mandatory = false;

    /** @var bool if true, the item is already in the selected pile when the user opens the question */
    private $preselected = false;

    /** @var string|null group identifier */
    private $group = null;

    /**
     * Assembles nette validation schema for question template data.
     */
    public static function schemaOfTemplate()
    {
        return Expect::structure([
            'text' => BaseQuestion::schemaOfLocalizedText()->required(),
            'correctOrder' => Expect::int()->nullable()->required(),
            'mandatory' => Expect::bool()->required(),
            'preselected' => Expect::bool()->required(),
            'group' => Expect::string()->nullable()->required(),
        ])->castTo('array');
    }

    public function __construct(
        $text = null,
        ?int $correctOrder = null,
        bool $mandatory = false,
        bool $preselected = false,
        ?string $group = null
    ) {
        if ($text) {
            $this->setText($text);
        }
        $this->correctOrder = $correctOrder;
        $this->mandatory = $mandatory;
        $this->preselected = $preselected;
        $this->group = $group;
    }

    public function load(array $json)
    {
        $this->loadText($json, 'Corrupted question data');
        $this->correctOrder = $json['correctOrder'];
        $this->mandatory = $json['mandatory'];
        $this->preselected = $json['preselected'];
        $this->group = $json['group'];
    }

    public function jsonSerialize(): mixed
    {
        return [
            'text' => $this->text,
            'correctOrder' => $this->correctOrder,
            'mandatory' => $this->mandatory,
            'preselected' => $this->preselected,
            'group' => $this->group,
        ];
    }

    /*
     * Getters
     */

    public function isCorrect(): bool
    {
        return $this->correctOrder !== null;
    }

    public function getCorrectOrder(): ?int
    {
        return $this->correctOrder;
    }

    public function isMandatory(): bool
    {
        return $this->mandatory;
    }

    public function isPreselected(): bool
    {
        return $this->preselected;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }
}
