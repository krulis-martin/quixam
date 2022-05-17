<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\Helpers\IQuestion;
use App\Helpers\QuestionFactory;
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
    use LocalizableEntity;

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
     * An index by which the questions are ordered when shown to the user.
     * This should form a continuous sequence (1..N) within one test.
     * @ORM\Column(type="integer")
     */
    protected $ordering;

    /**
     * Maximal points awarded for this question (copied from the template group).
     * @ORM\Column(type="integer")
     */
    protected $points;

    /**
     * Type identifier (corresponds to the question processor class).
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * Localized caption (topic) of the quesion for making listings more memorable
     * (copied from question template).
     * @ORM\Column(type="string")
     */
    protected $caption;

    /**
     * JSON-encoded data of the question (data are type-specific).
     * @ORM\Column(type="text", length=65535)
     */
    protected $data = '';

    /**
     * @param EnrolledUser $enrolledUser
     * @param TemplateQuestionsGroup $templateGroup
     * @param TemplateQuestion $templateQuestion
     * @param int $ordering
     * @param mixed $data (that will be serialized in JSON)
     */
    public function __construct(
        EnrolledUser $enrolledUser,
        TemplateQuestionsGroup $templateGroup,
        TemplateQuestion $templateQuestion,
        int $ordering,
        $data
    ) {
        $this->createdAt = new DateTime();
        $this->answers = new ArrayCollection();
        $this->enrolledUser = $enrolledUser;
        $this->templateQuestionsGroup = $templateGroup;
        $this->templateQuestion = $templateQuestion;
        $this->ordering = $ordering;
        $this->points = $templateGroup->getPoints();
        $this->type = $templateQuestion->getType();
        $this->caption = $templateQuestion->getCaptionRaw();
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

    public function setLastAnswer(Answer $answer): void
    {
        $this->lastAnswer = $answer;
    }

    public function getOrdering(): int
    {
        return $this->ordering;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCaption(string $locale, bool $strict = false): string
    {
        return $this->getLocalizedProperty('caption', $locale, $strict);
    }

    public function getData()
    {
        if (!$this->data) {
            return null;
        }

        return json_decode($this->data, true);
    }

    public function getQuestion(QuestionFactory $factory): IQuestion
    {
        $question = $factory->create($this->getType());
        $question->load($this->getData());
        return $question;
    }
}
