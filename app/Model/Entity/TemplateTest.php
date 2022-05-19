<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use DateTime;

/**
 * Representes a template from which one type of tests is generated.
 * This entity roughly corresponds to a course.
 *
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 * @ORM\Table(indexes={
 *   @ORM\Index(name="external_id", columns={"external_id"})
 * })
 */
class TemplateTest
{
    use CreateableEntity;
    use DeleteableEntity;
    use LocalizableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="TemplateQuestionsGroup", mappedBy="test")
     * @ORM\OrderBy({"ordering" = "ASC"})
     */
    protected $questionsGroups;

    /**
     * Localized caption of the test displayed to the users.
     * @ORM\Column(type="string")
     */
    protected $caption;

    /**
     * Optional external identification of this type of tests.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $externalId;

    /**
     * Optional external identification of the course to which this test template belongs to.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $courseId;

    /**
     * @param array|string $caption as a string with English caption or an array with [ locale => translation ]
     * @param string|null $externalId
     * @param string|null $courseId
     */
    public function __construct($caption, ?string $externalId = null, ?string $courseId = null)
    {
        $this->createdAt = new DateTime();
        $this->questionsGroups = new ArrayCollection();
        $this->externalId = $externalId;
        $this->courseId = $courseId;

        if ($caption) {
            if (!is_array($caption)) {
                $caption = [ 'en' => (string)$caption ];
            }
            $this->overwriteCaption($caption);
        }
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getQuestionGroups(): Collection
    {
        return $this->questionsGroups;
    }

    public function getCaption(string $locale, bool $strict = false): string
    {
        return $this->getLocalizedProperty('caption', $locale, $strict);
    }

    public function setCaption(string $locale, string $value): void
    {
        $this->setLocalizedProperty('caption', $locale, $value);
    }

    public function overwriteCaption(array $translations): void
    {
        $this->overwriteLocalizedProperty('caption', $translations);
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getCourseId(): ?string
    {
        return $this->courseId;
    }

    public function setCourseId(?string $courseId): void
    {
        $this->courseId = $courseId;
    }
}
