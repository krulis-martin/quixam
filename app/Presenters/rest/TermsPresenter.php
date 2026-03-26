<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\Entity\EnrollmentRegistration;
use App\Model\Entity\TemplateTest;
use App\Model\Entity\TestTerm;
use App\Model\Entity\User;
use App\Model\Repository\EnrollmentRegistrations;
use App\Model\Repository\TestTerms;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\Users;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use DateTime;
use Throwable;

/**
 * A presenter that provides REST API for managing test terms.
 * The presenter is accessible only for teachers and admins.
 */
final class RestTermsPresenter extends RestPresenter
{
    /** @var EnrollmentRegistrations @inject */
    public $enrollmentRegistrations;

    /** @var TestTerms @inject */
    public $terms;

    /** @var TemplateTests @inject */
    public $templateTests;

    /** @var Users @inject */
    public $users;

    protected function startup()
    {
        parent::startup();
        if ($this->user->getRole() === User::ROLE_STUDENT) {
            $this->error("The endpoint is not accessible for students.", 403);
        }
    }

    /**
     * Return all terms the user manages or supervises.
     */
    public function actionDefault(?string $testId = null): void
    {
        if ($this->user->isAdmin()) {
            $criteria = ['archivedAt' => null];
            if ($testId) {
                $testTemplate = $this->templateTests->findOneBy(['externalId' => $testId]);
                if (!$testTemplate) {
                    throw new NotFoundException("Test template with ID $testId not found.");
                }
                $criteria['template'] = $testTemplate;
            }
            $terms = $this->terms->findBy($criteria);
        } else {
            $terms = $this->terms->getTermsUserSupervisesOrManages($this->user, $testId);
        }
        $this->sendSuccessResponse($terms);
    }

    public function checkAddTerm(string $testId): void
    {
        /** @var TemplateTest|null $test */
        $test = $this->templateTests->findOneBy(['externalId' => $testId]);
        if (!$test) {
            throw new NotFoundException("Test template with ID $testId not found.");
        }

        if (!$test->isOwner($this->user)) {
            throw new ForbiddenRequestException("You are not an owner of the test template $testId.");
        }
    }

    /**
     * Add a new term based on given test template. If the term exists (identified by external ID),
     * update it instead. The term must not have started or been archived.
     * @param string $testId external ID of the test template
     * POST parameters:
     * - externalId (?string): external ID of the term (optional)
     * - scheduledAt (?int): timestamp when the term is scheduled to start (optional)
     * - location (?string): location of the term (optional)
     * - note (?array): localized note for the term [ locale => text ] (optional)
     * - supervisors (?array): array of users that will be associated as supervisors;
     *                         each value is an associative array with exactly one of the keys
     *                         "id", "externalId", or "email", that identifies the user
     */
    public function actionAddTerm(string $testId): void
    {
        // prepare data from the body
        $externalId = !empty($this->body['externalId']) ? trim($this->body['externalId']) : null;
        $scheduledAt = !empty($this->body['scheduledAt'])
            ? (new DateTime())->setTimestamp($this->body['scheduledAt']) : null;
        $location = !empty($this->body['location']) ? trim($this->body['location']) : '';
        $note = $this->body['note'] ?? null;

        // get the supervisors first, so we are sure they exist
        $supervisors = [];
        foreach ($this->body['supervisors'] ?? [] as $u) {
            if (!is_array($u) || count($u) !== 1 || !in_array(array_keys($u)[0], ['id', 'email', 'externalId'])) {
                throw new BadRequestException(
                    "User is identified by an array with exactly one of the keys 'id', 'email', or 'externalId'"
                );
            }
            $supervisors[] = $this->users->findByMultiOrThrow(
                $u['id'] ?? null,
                $u['email'] ?? null,
                $u['externalId'] ?? null
            );
        }

        // create or update the term
        $term = $externalId ? $this->terms->findOneBy(['externalId' => $externalId]) : null;
        if ($term) {
            // update existing
            if ($term->getStartedAt() !== null || $term->getArchivedAt() !== null) {
                throw new BadRequestException("Only terms that have not started or been archived can be updated.");
            }
            if ($term->getTemplate()->getExternalId() !== $testId) {
                throw new BadRequestException("External ID $externalId is already used by another term.");
            }

            $term->setScheduledAt($scheduledAt);
            $term->setLocation($location);
            if (!is_array($note)) {
                $note = ['en' => (string)$note];
            }
            $term->overwriteNote($note);
            $term->removeAllSupervisors(); // so they can be re-filled later
        } else {
            // create new
            $testTemplate = $this->templateTests->findOneBy(['externalId' => $testId]);
            try {
                $term = new TestTerm($testTemplate, $scheduledAt, $location, $externalId, $note);
            } catch (Throwable $e) {
                throw new BadRequestException("Invalid input data: " . $e->getMessage());
            }
        }

        // fill in the supervisors
        foreach ($supervisors as $s) {
            $term->addSupervisor($s);
        }

        $this->terms->persist($term);
        $this->sendSuccessResponse($term);
    }

    public function checkRemoveTerm(string $id): void
    {
        $term = $this->terms->findOrThrow($id);
        if ($term->getArchivedAt() !== null) {
            throw new BadRequestException("Cannot remove an archived term.");
        }
        if ($term->getEnrolledUsers()->count() > 0) {
            throw new BadRequestException("Cannot remove a term with enrolled users.");
        }
        if (!$term->getTemplate()->isOwner($this->user)) {
            throw new ForbiddenRequestException("You are not an owner of the test template of term $id.");
        }
    }

    /**
     * Remove a term. The term must not have any registered or enrolled users.
     * @param string $id internal ID of the term to be removed
     */
    public function actionRemoveTerm(string $id): void
    {
        $term = $this->terms->findOrThrow($id);
        $this->terms->remove($term);
        $this->sendSuccessResponse("OK");
    }

    public function checkRegisterUsers(string $id): void
    {
        $term = $this->terms->findOrThrow($id);
        if ($term->getArchivedAt() !== null) {
            throw new BadRequestException("Cannot register users for an archived term.");
        }
        if ($term->getFinishedAt() !== null) {
            throw new BadRequestException("Cannot register users for a term that has already finished.");
        }
        if (!$term->getTemplate()->isOwner($this->user)) {
            throw new ForbiddenRequestException("You are not an owner of the test template of term $id.");
        }
    }

    /**
     * Register users for a term. The term must not have started yet.
     * @param string $id internal ID of the term for which the users should be registered
     * POST parameters:
     * - users: array of user to be registered;
     *          each value is an associative array with exactly one of the keys
     *          "id", "externalId", or "email", that identifies the user
     */
    public function actionRegisterUsers(string $id): void
    {
        $term = $this->terms->findOrThrow($id);

        // prepare registration entities from the data in the body
        $registrations = [];
        foreach ($this->body['users'] ?? [] as $u) {
            if (!$u || !is_array($u)) {
                throw new BadRequestException(
                    "User is identified by an array with at least one of the keys 'id', 'email', or 'externalId'"
                );
            }

            foreach (array_keys($u) as $key) {
                if (!in_array($key, ['id', 'email', 'externalId'])) {
                    throw new BadRequestException(
                        "User is identified by an array with at least one of the keys 'id', 'email', or 'externalId'"
                    );
                }
            }

            $user = $this->users->findByMulti(
                $u['id'] ?? null,
                $u['email'] ?? null,
                $u['externalId'] ?? null
            );
            if (!$user && empty($u['externalId']) && empty($u['email'])) {
                throw new NotFoundException(
                    "User not found and no external identification was provided.\n" . json_encode($u)
                );
            }

            if ($user && $term->getEnrolledUsers()->contains($user)) {
                continue; // skipping, the user is already enrolled
            }

            // create or update the registration
            $externalId = $u['externalId'] ?? null;
            $email = $u['email'] ?? null;
            $reg = $this->enrollmentRegistrations->findOrCreateRegistration($term, $user, $externalId, $email);
            if ($externalId) {
                $reg->setExternalId($u['externalId']);
            }
            if ($email) {
                $reg->setEmail($u['email']);
            }
            $registrations[] = $reg;
        }

        // persist the registrations
        foreach ($registrations as $r) {
            $this->enrollmentRegistrations->persist($r, false);
        }
        $this->enrollmentRegistrations->flush();
        $this->sendSuccessResponse("OK");
    }
}
