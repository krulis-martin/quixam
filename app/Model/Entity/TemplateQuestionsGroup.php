<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use DateTime;

/**
 * A group of question templates from which a certain amount of questions is selected.
 * This roughly corresponds to one topic or type of questions being tested.
 *
 * @ORM\Entity
 * @ORM\Table(indexes={
 *   @ORM\Index(name="external_id", columns={"external_id"})
 * })
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class TemplateQuestionsGroup
{
    use CreatableEntity;
    use DeletableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateQuestionsGroup")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateTest", inversedBy="questionsGroups")
     */
    protected $test;

    /**
     * @ORM\OneToMany(targetEntity="TemplateQuestion", mappedBy="questionsGroup")
     */
    protected $questions;

    /**
     * An index by which the question groups are ordered when a test is instantiated.
     * (the lower ordering value, the sooner the questions will appear).
     * @ORM\Column(type="integer")
     */
    protected $ordering;

    /**
     * How many questions should be selected from this group.
     * @ORM\Column(type="integer")
     */
    protected $selectCount;

    /**
     * Prescribed points for each question instantiated from this group.
     * If points per item are not null, this is the base/offset to/from the points per item are added/subtracted.
     * Otherwise, this is the total points awarded for the question.
     * If positive, the points are awarded for the correct answer.
     * If negative, the points are awarded for the mistakes (negative grading).
     * @ORM\Column(type="integer")
     */
    protected $points;

    /**
     * Prescribed points per item (awarded for each question sub-item) for each question instantiated from this group.
     * The $points value is taken as the base/offset.
     * In the case of negative value, points per item are subtracted from the base/offset for each mistake.
     * In the case of positive value, points per item are added to the base/offset for each correct item.
     * If zero, the question is graded only as entirely correct or entirely wrong.
     * Note: this is copied value from template group entity.
     * @ORM\Column(type="integer")
     */
    protected $pointsPerItem;

    /**
     * External identification of this questions group.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $externalId;

    /**
     * @param TemplateTest $test in which this group will belong to
     * @param int $ordering
     * @param int $selectCount
     * @param int $points
     * @param int $pointsPerItem
     * @param string|null $externalId
     * @param TemplateQuestionsGroup|null $createdFrom previous instance if new instance is a replacement
     */
    public function __construct(
        TemplateTest $test,
        int $ordering,
        int $selectCount = 1,
        int $points = 1,
        int $pointsPerItem = 0,
        ?string $externalId = null,
        ?TemplateQuestionsGroup $createdFrom = null
    ) {
        $this->createdAt = new DateTime();
        $this->test = $test;
        $this->ordering = $ordering;
        $this->selectCount = $selectCount;
        $this->points = $points;
        $this->pointsPerItem = $pointsPerItem;
        $this->externalId = $externalId;
        $this->createdFrom = $createdFrom;
        $this->questions = new ArrayCollection();
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getCreatedFrom(): ?TemplateQuestionsGroup
    {
        return $this->createdFrom;
    }

    public function getTest(): TemplateTest
    {
        return $this->test;
    }

    public function getOrdering(): int
    {
        return $this->ordering;
    }

    public function getSelectCount(): int
    {
        return $this->selectCount;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getPointsPerItem(): int
    {
        return $this->pointsPerItem;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getQuestions(): Collection
    {
        return $this->questions->filter(
            function (TemplateQuestion $question) {
                return $question->getDeletedAt() === null;
            }
        );
    }
}
