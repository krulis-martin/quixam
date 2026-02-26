<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * Represents single submitted answer for one question.
 * All answers are collected so we can keep some track of user's progress;
 * however, only the last answer is evaluated.
 *
 * @ORM\Entity
 */
class Answer
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
     * @ORM\ManyToOne(targetEntity="Question", inversedBy="answers")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    protected $question;

    /**
     * The answer is JSON encoded and question-type specific.
     * @ORM\Column(type="text", length=65535)
     */
    protected $answer;

    /**
     * Time when the answer was evaluated.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $evaluatedAt = null;

    /**
     * Last time the evaluation was updates (e.g., teacher changed the points or comment).
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $evaluationUpdatedAt = null;

    /**
     * Points awarded for this answer.
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $points = null;

    /**
     * Points automatically assigned by the system for this answer.
     * This may be identical as points; different if the teacher manually changed the points,
     * or null if the system did not assign any points (the question is manually graded).
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $autoPoints = null;

    /**
     * Correctness (0.0-1.0) assigned by the system for this answer.
     * This is only applicable for some answers (like open questions), and is null for the others.
     * @ORM\Column(type="float", nullable=true)
     */
    protected $correctness = null;

    /**
     * Comment assigned by the teacher to this answer, visible to the student.
     * @ORM\Column(type="text", length=65535)
     */
    protected $publicComment = "";

    /**
     * Comment assigned by the teacher to this answer, visible only to the teachers.
     * @ORM\Column(type="text", length=65535)
     */
    protected $privateComment = "";

    /**
     * IP address of the client who submitted this answer (as perceived by the server).
     * @ORM\Column(type="string")
     */
    protected $ipAddress = "";

    public function __construct(Question $question, $answer, string $ipAddress = "")
    {
        $this->createdAt = new DateTime();
        $this->question = $question;
        $this->answer = json_encode($answer);
        $this->ipAddress = $ipAddress;
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function getAnswer()
    {
        return json_decode($this->answer, true);
    }

    public function isEvaluated(): bool
    {
        return $this->evaluatedAt !== null && $this->points !== null;
    }

    public function getEvaluatedAt(): ?DateTime
    {
        return $this->evaluationUpdatedAt ?? $this->evaluatedAt;
    }

    public function isEvaluationUpdated(): bool
    {
        return $this->evaluationUpdatedAt !== null;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function getAutoPoints(): ?int
    {
        return $this->autoPoints;
    }

    public function setPoints(int $points, bool $auto = true): void
    {
        if ($this->evaluatedAt === null) {
            $this->evaluatedAt = new DateTime();
        } else {
            $this->evaluationUpdatedAt = new DateTime();
        }
        $this->points = $points;
        if ($auto) {
            $this->autoPoints = $points;
        }
    }

    public function getCorrectness(): ?float
    {
        return $this->correctness;
    }

    public function setCorrectness(float $correctness): void
    {
        $this->correctness = $correctness;
    }

    public function getPublicComment(): string
    {
        return $this->publicComment;
    }

    public function setPublicComment(string $publicComment): void
    {
        $this->publicComment = $publicComment;
    }

    public function getPrivateComment(): string
    {
        return $this->privateComment;
    }

    public function setPrivateComment(string $privateComment): void
    {
        $this->privateComment = $privateComment;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
}
