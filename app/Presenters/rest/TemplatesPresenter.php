<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\TemplatesActions;
use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestionsGroup;
use Throwable;

/**
 * A presenter that provides REST API for managing test templates.
 */
final class RestTemplatesPresenter extends RestPresenter
{
    /** @var TemplatesActions @inject */
    public $templatesActions;

    /**
     * Get a test template by its external ID and check if the user is an owner of the template.
     * Throws an exception if the test does not exist or the user is not an owner.
     */
    private function getTemplateTest(string $externalId): TemplateTest
    {
        $test = $this->templatesActions->getTemplateTest($externalId);
        if (!$test) {
            throw new NotFoundException("Test template '$externalId' does not exist.");
        }
        if (!$this->user->isAdmin() && !$test->isOwner($this->user)) {
            throw new ForbiddenRequestException("You are not an owner of '$externalId' test template.");
        }
        return $test;
    }

    private function getTemplateQuestionsGroup(TemplateTest $test, $groupId): TemplateQuestionsGroup
    {
        $group = $this->templatesActions->getTemplateGroup($test, $groupId);
        if (!$group) {
            throw new NotFoundException("Template questions group '$groupId' does not exist.");
        }
        return $group;
    }

    private function getIntParam(string $name, bool $positive = false): ?int
    {
        $value = $this->body[$name] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new BadRequestException("Parameter $name is expected to have numeric value, but '$value' was given.");
        }

        $value = (int)$value;
        if ($positive && $value < 1) {
            throw new BadRequestException("Value of $name is expected to be positive, but '$value' was given.");
        }

        return $value;
    }

    /**
     * Get caption from body and assemble localized caption array.
     * @return array [ 'en' => 'English caption', 'cs' => 'Czech caption', ... ]
     */
    protected function getCaption(): array
    {
        if (($this->body['caption_en'] ?? null) === null) {
            throw new BadRequestException("English caption must be present.");
        }

        $caption = ['en' => trim($this->body['caption_en'])];
        if (($this->body['caption_cs'] ?? null) !== null) {
            $caption['cs'] = trim($this->body['caption_cs']);
        }
        return $caption;
    }

    /**
     * Get a test template by its external ID.
     * @param string $id external ID of the test template
     */
    public function actionGetTest(string $id): void
    {
        $test = $this->getTemplateTest($id);
        $this->sendSuccessResponse($this->templatesActions->getTemplateTestStructure($test));
    }

    /**
     * Add a new group to the test template or update an existing one.
     * @param string $id external ID of the test template
     * @param string $groupId external ID of the question group
     * The request body may contain the following parameters:
     * - points: number of points awarded for each question in this group (optional)
     * - count: number of questions selected from this group (optional)
     * - ordering: an index used for sorting the groups when the test is being assembled (optional)
     */
    public function actionAddGroup(string $id, string $groupId): void
    {
        $test = $this->getTemplateTest($id);
        $ordering = $this->getIntParam('ordering', true);
        $count = $this->getIntParam('count', true);
        $points = $this->getIntParam('points');

        $res = $this->templatesActions->addGroup($test, $groupId, $ordering, $count, $points);
        $this->sendSuccessResponse([
            'created' => $res === null,
            'updated' => $res === false,
        ]);
    }

    /**
     * Delete a group from the test template.
     * @param string $id external ID of the test template
     * @param string $groupId external ID of the question group
     */
    public function actionDeleteGroup(string $id, string $groupId): void
    {
        $test = $this->getTemplateTest($id);
        $deleted = $this->templatesActions->deleteGroup($test, $groupId);
        $this->sendSuccessResponse($deleted);
    }

    /**
     * Add a new question to the template questions group or update an existing one.
     * @param string $id external ID of the test template
     * @param string $groupId external ID of the question group
     * @param string $questionId external ID of the question
     */
    public function actionAddQuestion(string $id, string $groupId, string $questionId): void
    {
        $test = $this->getTemplateTest($id);
        $group = $this->getTemplateQuestionsGroup($test, $groupId);

        $type = $this->body['type'] ?? '';
        $data = $this->body['data'] ?? null;
        try {
            $this->templatesActions->checkQuestionData($type, $data);
        } catch (Throwable $e) {
            throw new BadRequestException("Invalid question data: " . $e->getMessage());
        }

        $res = $this->templatesActions->addQuestion(
            $test,
            $group,
            $questionId,
            $this->getCaption(),
            $type,
            $data,
        );

        $this->sendSuccessResponse([
            'created' => $res === null,
            'updated' => $res === false,
        ]);
    }

    /**
     * Delete a question from the template questions group.
     * @param string $id external ID of the test template
     * @param string $groupId external ID of the question group
     * @param string $questionId external ID of the question
     */
    public function actionDeleteQuestion(string $id, string $groupId, string $questionId): void
    {
        $test = $this->getTemplateTest($id);
        $group = $this->getTemplateQuestionsGroup($test, $groupId);
        $deleted = $this->templatesActions->deleteQuestion($test, $group, $questionId);
        $this->sendSuccessResponse($deleted);
    }
}
