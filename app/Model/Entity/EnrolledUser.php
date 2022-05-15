<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
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
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TestTerm")
     */
    protected $test;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="enrolledForTests")
     */
    protected $user;

    /**
     * @ORM\OneToMany(targetEntity="Question", mappedBy="enrolledUser")
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
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxScore = null;

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
        $this->seed = random_int(0, PHP_INT_MAX);
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
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

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function hasScore(): bool
    {
        return $this->score !== null && $this->maxScore !== null;
    }

    public function getMaxScore(): ?int
    {
        return $this->maxScore;
    }

    public function setScore(int $score, int $maxScore)
    {
        $this->score = $score;
        $this->maxScore = $maxScore;
    }
}