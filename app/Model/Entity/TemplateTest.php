<?php

declare(strict_types=1);

namespace App\Model\Entity;

use App\Helpers\Grading;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use DateTime;

/**
 * Represents a template from which one type of tests is generated.
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
    use CreatableEntity;
    use DeletableEntity;
    use LocalizableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
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
     * Optional JSON structure with grading limits. Must be an associative array (collection), where keys are marks
     * and values are point limits (integers). The point limits are lower bounds, the nearest one determines the mark.
     * E.g., [ 1 => 17, 2 => 14, 3 => 11, 4 => 0 ]: 17+ => mark 1, 14-16 => mark 2, 11-13 => mark 3, else => 4
     * @ORM\Column(type="string")
     */
    protected $grading;

    /**
     * Internal cache for deserialized grading object (that wraps the grading algorithm).
     * @var Grading|null
     */
    private $gradingObj = null;

    /**
     * @param array|string $caption as a string with English caption or an array with [ locale => translation ]
     * @param string|null $externalId
     * @param string|null $courseId
     * @param Grading $grading
     */
    public function __construct($caption, ?string $externalId = null, ?string $courseId = null, Grading $grading = null)
    {
        $this->createdAt = new DateTime();
        $this->questionsGroups = new ArrayCollection();
        $this->externalId = $externalId;
        $this->courseId = $courseId;

        if ($caption) {
            if (!is_array($caption)) {
                $caption = ['en' => (string)$caption];
            }
            $this->overwriteCaption($caption);
        }

        $this->gradingObj = $grading ?? new Grading();
        $this->grading = json_encode($this->gradingObj);
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return (string)$this->id;
    }

    public function getQuestionGroups(): Collection
    {
        return $this->questionsGroups->filter(
            function (TemplateQuestionsGroup $group) {
                return $group->getDeletedAt() === null;
            }
        );
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

    public function getGradingRaw(): string
    {
        return $this->grading;
    }

    public function getGrading(): Grading
    {
        if ($this->gradingObj === null) {
            $grading = $this->grading ? json_decode($this->grading, true) : [];
            $this->gradingObj = new Grading($grading);
        }
        return $this->gradingObj;
    }
}
