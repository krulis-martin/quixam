<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use DateTime;

/**
 * Represents one enrolled user for a particular test instance.
 * This entity also holds total points that have been scored to the user.
 *
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"test_id", "user_id"})})
 */
class EnrolledUser
{
    use CreatableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TestTerm")
     */
    protected $test;

    /**
     * @ORM\ManyToOne(targetEntity="User", fetch="EAGER")
     */
    protected $user;

    /**
     * @ORM\OneToMany(targetEntity="Question", mappedBy="enrolledUser", cascade={"persist", "remove"})
     * @ORM\OrderBy({"ordering" = "ASC"})
     */
    protected $questions;

    /**
     * A random seed used for the MT generator when the questions were instantiated.
     * @ORM\Column(type="integer")
     */
    protected $seed;

    /**
     * Total points scored in this test.
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $score = null;

    /**
     * Maximal possible number of points that can be scored in this test.
     * This is valid only for regular scoring, negative scoring has no explicit maximum.
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxScore = null;

    /**
     * When the enrolled user gets locked, no additional answers can be submitted by the corresponding user.
     * @ORM\Column(type="boolean")
     */
    protected $locked = false;

    /**
     * @param TestTerm $test
     * @param User $user
     */
    public function __construct(TestTerm $test, User $user)
    {
        $this->createdAt = new DateTime();
        $this->test = $test;
        $this->user = $user;
        $this->questions = new ArrayCollection();
        $this->seed = random_int(0, 2000000000);
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getTest(): TestTerm
    {
        return $this->test;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function hasScore(): bool
    {
        return $this->score !== null && $this->maxScore !== null;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getMaxScore(): ?int
    {
        return $this->maxScore;
    }

    public function setMaxScore(int $maxScore): void
    {
        $this->maxScore = $maxScore;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked($locked = true): void
    {
        $this->locked = $locked;
    }

    /**
     * Return a grade based on given score and test grading system.
     * @return string|int|null null if the grade cannot be determined
     */
    public function getGrade()
    {
        $grading = $this->test->getGrading();
        return $this->score !== null ? $grading->getGrade($this->score) : $this->score;
    }

    public function getGradeColor(): ?string
    {
        $grading = $this->test->getGrading();
        return $this->score !== null ? $grading->getGradeColor($this->score) : $this->score;
    }
}
