<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
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
    use CreateableEntity;

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
     * @ORM\Column(type="string")
     */
    protected $answer;

    /**
     * Time when the answer was evaluated.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $evaluatedAt = null;

    /**
     * Points awarded for this answer.
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $points = null;

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
        return $this->evaluatedAt;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->evaluatedAt = new DateTime();
        $this->points = $points;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
}
