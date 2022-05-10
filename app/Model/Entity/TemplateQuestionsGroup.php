<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;

/**
 * A group of question templates from which a certain amount of questions is selected.
 * This roughly corresponds to one topic or type of questions being tested.
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"test_id", "external_id"})})
 */
class TemplateQuestionsGroup
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
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
     * @ORM\OneToMany(targetEntity="TemplateQuestion", mappedBy="questionGroup")
     */
    protected $questions;

    /**
     * An order in which the question groups are applied when a test is instantiated.
     * (the lower order value, the sooner the questions will appear).
     * @ORM\Column(type="integer")
     */
    protected $order;

    /**
     * How many questions should be selected from this group.
     * @ORM\Column(type="integer")
     */
    protected $count;

    /**
     * Points awarded for each question from this group.
     * @ORM\Column(type="integer")
     */
    protected $points;

    /**
     * External identification of this questions group.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $externalId;

    /**
     * @param TemplateTest $test in which this group will belong to
     * @param int $count
     * @param int $points
     * @param string|null $externalId
     * @param TemplateQuestionsGroup|null $createdFrom previous instance if new instance is a replacement
     */
    public function __construct(
        TemplateTest $test,
        int $order,
        int $count = 1,
        int $points = 1,
        ?string $externalId = null,
        ?TemplateQuestionsGroup $createdFrom = null
    ) {
        $this->createdAt = new DateTime();
        $this->test = $test;
        $this->order = $order;
        $this->count = $count;
        $this->points = $points;
        $this->externalId = $externalId;
        $this->createdFrom = $createdFrom;
        $this->questions = new ArrayCollection();
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedFrom(): ?TemplateQuestionsGroup
    {
        return $this->createdFrom;
    }

    public function getTest(): TemplateTest
    {
        return $this->test;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getQuestions(): Collection
    {
        return $this->questions;
    }
}
