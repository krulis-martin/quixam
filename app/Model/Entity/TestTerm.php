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
 * An instance of a test which is taken at given time and place.
 * The states of the test are determined by scheduledAt, startedAt, and finishedAt timestamps
 * (more precisely, whether they are still null or not).
 *
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 */
class TestTerm
{
    use CreateableEntity;
    use DeleteableEntity;
    use LocalizedEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TemplateTest")
     */
    protected $template;

    /**
     * @ORM\ManyToMany(targetEntity="User")
     * @ORM\JoinTable(name="test_term_supervisor")
     */
    protected $supervisors;

    /**
     * @ORM\OneToMany(targetEntity="EnrolledUser", mappedBy="test")
     */
    protected $enrolledUsers;

    /**
     * @ORM\OneToMany(targetEntity="EnrollmentRegistration", mappedBy="test")
     */
    protected $registrations;

    /**
     * Time when the test is supposed to start.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $scheduledAt = null;

    /**
     * Time when the test was actually started.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt = null;

    /**
     * Time when the test was finished.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $finishedAt = null;

    /**
     * Time when the test was move to archive (students no longer see the results).
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $archivedAt = null;

    /**
     * Address or room number that specifies location where the test is taken.
     * @ORM\Column(type="string")
     */
    protected $location;

    /**
     * Additional localized note from the supervisor to the students.
     * @ORM\Column(type="string")
     */
    protected $note;

    /**
     * @param TemplateTest $template
     * @param DateTime|null $scheduledAt
     * @param string $location
     * @param string|array $note
     */
    public function __construct(
        TemplateTest $template,
        ?DateTime $scheduledAt = null,
        string $location = '',
        $note = ''
    ) {
        $this->createdAt = new DateTime();
        $this->template = $template;
        $this->supervisors = new ArrayCollection();
        $this->enrolledUsers = new ArrayCollection();
        $this->registrations = new ArrayCollection();
        $this->scheduledAt = $scheduledAt;
        $this->location = $location;

        if (!is_array($note)) {
            $note = [ 'en' => (string)$note ];
        }
        $this->overwriteNote($note);
    }

    /*
     * Accessors
     */

    public function getId(): string
    {
        return $this->id;
    }

    public function getTemplate(): TemplateTest
    {
        return $this->template;
    }

    public function getCaption(string $locale, bool $strict = false): string
    {
        return $this->getTemplate()->getLocalizedProperty('caption', $locale, $strict);
    }

    public function getExternalId(): ?string
    {
        return $this->getTemplate()->getExternalId();
    }

    public function getCourseId(): ?string
    {
        return $this->getTemplate()->getCourseId();
    }

    public function getSupervisors(): Collection
    {
        return $this->supervisors;
    }

    public function getEnrolledUsers(): Collection
    {
        return $this->enrolledUsers;
    }

    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function getScheduledAt(): ?DateTime
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?DateTime $scheduledAt): void
    {
        $this->scheduledAt = $scheduledAt;
    }

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTime $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getFinishedAt(): ?DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTime $finishedAt): void
    {
        $this->finishedAt = $finishedAt;
    }

    public function getArchivedAt(): ?DateTime
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?DateTime $archivedAt): void
    {
        $this->archivedAt = $archivedAt;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location)
    {
        $this->location = $location;
    }

    public function getNote(string $locale, bool $strict = false): string
    {
        return $this->getLocalizedProperty('note', $locale, $strict);
    }

    public function setNote(string $locale, string $value): void
    {
        $this->setLocalizedProperty('note', $locale, $value);
    }

    public function overwriteNote(array $translations): void
    {
        $this->overwriteLocalizedProperty('note', $translations);
    }

    /*
     * Other modifiers
     */

    public function addSupervisor(User $supervisor): void
    {
        $this->supervisors->add($supervisor);
    }

    public function removeSupervisor(User $supervisor): void
    {
        $this->supervisors->remove($supervisor);
    }
}
