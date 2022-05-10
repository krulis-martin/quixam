<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"test_id", "external_id"})})
 */
class TemplateQuestion
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateQuestion")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateTest")
     */
    protected $test;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateQuestionsGroup", inversedBy="questions")
     */
    protected $questionsGroup;

    /**
     * External identification of this questions (must be unique within the whole test template).
     * @ORM\Column(type="string", nullable=true)
     */
    protected $externalId;

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
     * @param TemplateQuestionsGroup $group where the question belongs to
     * @param string $type
     * @param mixed $data (that will be serialized in JSON)
     * @param string|null $externalId
     * @param TemplateQuestion|null $createdFrom previous instance if new instance is a replacement
     */
    public function __construct(
        TemplateQuestionsGroup $group,
        string $type,
        $data,
        ?string $externalId = null,
        ?TemplateQuestion $createdFrom = null
    ) {
        $this->createdAt = new DateTime();
        $this->questionsGroup = $group;
        $this->test = $group->getTest(); // optimization, so we can have unique index
        $this->type = $type;
        $this->data = ($data === null) ? '' : json_encode($data);
        $this->externalId = $externalId;
        $this->createdFrom = $createdFrom;
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

    public function getQuestionsGroup(): TemplateQuestionsGroup
    {
        return $this->questionsGroup;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getData()
    {
        if (!$this->data) {
            return null;
        }

        return json_decode($this->data);
    }
}
