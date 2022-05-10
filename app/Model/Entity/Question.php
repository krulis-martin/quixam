<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;

/**
 * An instance of one question in a particular test for a particular user.
 *
 * @ORM\Entity
 */
class Question
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="EnrolledUser", inversedBy="questions")
     */
    protected $enrolledUser;

    /**
     * Reference to template questions group that was actual at the time of creation.
     * @ORM\ManyToOne(targetEntity="TemplateQuestionsGroup")
     */
    protected $templateQuestionsGroup;

    /**
     * Reference to template question which was actual at the time of creation.
     * @ORM\ManyToOne(targetEntity="TemplateQuestion")
     */
    protected $templateQuestion;

    /**
     * @ORM\OneToMany(targetEntity="Answer", mappedBy="question")
     */
    protected $answers;

    /**
     * This is a reference to the last (by createdAt) answer to this question.
     * This is an optimization to speed up displaying of the results.
     *
     * @ORM\OneToOne(targetEntity="Answer", fetch="EAGER")
     * @var Answer|null
     */
    protected $lastAnswer = null;

    /**
     * An order in which the question groups are applied when a test is instantiated.
     * (the lower order value, the sooner the questions will appear).
     * @ORM\Column(type="integer")
     */
    protected $order;

    /**
     * Type identifier (corresponds to the question processor class).
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * JSON-encoded data of the question (data are type-specific).
     * @ORM\Column(type="text", length=65535)
     */
    protected $data = '';

    /**
     * @param EnrolledUser $enrolledUser
     * @param TemplateQuestionsGroup $templateGroup
     * @param TemplateQuestion $templateQuestion
     * @param int $order
     * @param string $type
     * @param mixed $data (that will be serialized in JSON)
     */
    public function __construct(
        EnrolledUser $enrolledUser,
        TemplateQuestionsGroup $templateGroup,
        TemplateQuestion $templateQuestion,
        int $order,
        string $type,
        $data
    ) {
        $this->createdAt = new DateTime();
        $this->answers = new ArrayCollection();
        $this->enrolledUser = $enrolledUser;
        $this->templateQuestionsGroup = $templateGroup;
        $this->templateQuestion = $templateQuestion;
        $this->order = $order;
        $this->type = $type;
        $this->data = ($data === null) ? '' : json_encode($data);
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getEnrolledUser(): EnrolledUser
    {
        return $this->enrolledUser;
    }

    public function getTemplateQuestionsGroup(): TemplateQuestionsGroup
    {
        return $this->templateQuestionsGroup;
    }

    public function getTemplateQuestion(): TemplateQuestion
    {
        return $this->templateQuestion;
    }

    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function getLastAnswer(): ?Answer
    {
        return $this->lastAnswer;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData()
    {
        if (!$this->data) {
            return null;
        }

        return json_decode($this->data);
    }
}
