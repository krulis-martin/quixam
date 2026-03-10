<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\Entity\TemplateTest;
use App\Model\Entity\TemplateQuestionsGroup;
use App\Model\Entity\TemplateQuestion;
use App\Model\Repository\TemplateTests;
use App\Model\Repository\TemplateQuestions;
use App\Model\Repository\TemplateQuestionsGroups;
use RuntimeException;

/**
 * Helper class that encapsulates actions related to templates.
 * It is used by console commands and REST API presenters.
 */
final class TemplatesActions
{
    /** @var TemplateTests */
    private $templateTests;

    /** @var TemplateQuestions */
    private $templateQuestions;

    /** @var TemplateQuestionsGroups */
    private $templateQuestionsGroups;

    /** @var QuestionFactory */
    private $questionFactory;

    public function __construct(
        TemplateTests $templateTests,
        TemplateQuestions $templateQuestions,
        TemplateQuestionsGroups $templateQuestionsGroups,
        QuestionFactory $questionFactory
    ) {
        $this->templateTests = $templateTests;
        $this->templateQuestions = $templateQuestions;
        $this->templateQuestionsGroups = $templateQuestionsGroups;
        $this->questionFactory = $questionFactory;
    }

    /**
     * Get a default for ordering parameter by scanning for existing groups and returning +1.
     * @param TemplateTest $test groups of which are scanned
     * @return int new ordering value for the created entity
     */
    private static function getMaxOrdering(TemplateTest $test): int
    {
        $groups = $test->getQuestionGroups();
        if ($groups->isEmpty()) {
            return 1;
        }
        return $groups->last()->getOrdering() + 1;
    }



    /**
     * Retrieves test template by its external ID.
     * @return TemplateTest|null Returns the template test entity or null if it does not exist.
     */
    public function getTemplateTest(string $externalId): ?TemplateTest
    {
        return $this->templateTests->findOneBy(['externalId' => $externalId]);
    }

    public function getTemplateGroup(TemplateTest $test, string $groupExternalId): ?TemplateQuestionsGroup
    {
        return $this->templateQuestionsGroups->findOneBy(['externalId' => $groupExternalId, 'test' => $test->getId()]);
    }

    /**
     * Returns template test structure (groups and questions).
     * @param TemplateTest $test The template test entity.
     * @return array [ groupExternalId => [questionExternalId, ...], ... ]
     */
    public function getTemplateTestStructure(TemplateTest $test): array
    {
        $result = [];
        foreach ($test->getQuestionGroups() as $group) {
            $questions = [];
            foreach ($group->getQuestions() as $question) {
                $questions[] = $question->getExternalId() ?? $question->getId();
            }
            $result[$group->getExternalId() ?? $group->getId()] = $questions;
        }
        return $result;
    }

    /**
     * Adds a new template questions group to the given test or updates it
     * if a group with the same external ID already exists.
     * @param TemplateTest $test The template test entity to which the group belongs.
     * @param string $groupId External ID of the group.
     * @param int|null $ordering An index used for sorting the groups when the test is being assembled.
     * @param int|null $count Number of questions selected from this group.
     * @param int|null $points Number of points awarded for each question in this group.
     * @return bool|null null = group needed to be created, false = group was updated, true = no changes made
     */
    public function addGroup(
        TemplateTest $test,
        string $groupId,
        ?int $ordering = null,
        ?int $count = null,
        ?int $points = null
    ): ?bool {
        $group = $this->templateQuestionsGroups->findOneBy(['externalId' => $groupId, 'test' => $test->getId()]);
        $ordering = $ordering ?? ($group ? $group->getOrdering() : self::getMaxOrdering($test));
        $count = $count ?? ($group ? $group->getSelectCount() : 1);
        $points = $points ?? ($group ? $group->getPoints() : 1);

        if (
            $group
            && $group->getOrdering() === $ordering
            && $group->getSelectCount() === $count
            && $group->getPoints() === $points
        ) {
            return true; // no update needed, the group already has the desired values.
        }

        $newGroup = new TemplateQuestionsGroup($test, $ordering, $count, $points, $groupId, $group);
        $this->templateQuestionsGroups->persist($newGroup);

        if (!empty($group)) {
            $this->templateQuestions->reconnectQuestions($group, $newGroup);
            $this->templateQuestionsGroups->remove($group);
            return false; // group was updated.
        } else {
            return null; // new group was created.
        }
    }

    /**
     * Checks the validity of question data based on its type.
     * @param string $type The type of the question.
     * @param array $data The question data to check.
     * @param int $thoroughness The number of random seeds used to test the question data.
     * @throws RuntimeException If the question data is invalid.
     */
    public function checkQuestionData(string $type, array $data, int $thoroughness = 20): void
    {
        // smoke tests
        if ($type) {
            // regular (static) question
            $questionData = $this->questionFactory->create($type);
            for ($seed = 0; $seed < $thoroughness; ++$seed) {
                $questionData->instantiate($data, $seed); // throws on error
            }
        } else {
            // dynamic (generated question)
            $dynamicQuestion = new DynamicQuestion($data, $this->questionFactory);
            $res = DynamicQuestion::validateCode($dynamicQuestion->getCode());
            if ($res !== true) {
                foreach (is_array($res) ? $res : [] as $error) {
                    echo "Code validation error: $error\n";
                }
                throw new RuntimeException("Code validation of dynamic question generator failed.");
            }

            for ($seed = 0; $seed < $thoroughness; ++$seed) {
                $dynamicQuestion = new DynamicQuestion($data, $this->questionFactory);
                $dynamicQuestion->generate($seed);
            }
        }
    }

    /**
     * Adds a new template question to the given group or updates it
     * if a question with the same external ID already exists.
     * @param TemplateTest $test The template test entity to which the question belongs.
     * @param TemplateQuestionsGroup $group The template questions group entity to which the question belongs.
     * @param string $questionId External ID of the question.
     * @param array $caption An array of localized captions, e.g. ['en' => 'English caption', 'cs' => 'Czech caption'].
     * @param string $type The type of the question, e.g. 'multiple-choice', 'short-answer', etc.
     * @param array|null $data The question data, structure of which depends on the question type.
     * @return bool|null null = question needed to be created, false = question was updated, true = no changes made
     */
    public function addQuestion(
        TemplateTest $test,
        TemplateQuestionsGroup $group,
        string $questionId,
        array $caption,
        string $type,
        ?array $data
    ): ?bool {
        $question = $this->templateQuestions->findOneBy([
            'externalId' => $questionId,
            'test' => $test->getId(),
            'questionsGroup' => $group->getId()
        ]);

        // prepare question data
        $captionJson = json_encode($caption);
        $dataJson = $data === null ? '' : json_encode($data);

        if (
            $question
            && $question->getCaptionRaw() === $captionJson
            && $question->getType() === $type
            && $question->getDataRaw() === $dataJson
        ) {
            return true; // no update needed, the question already has the desired values.
        }

        $newQuestion = new TemplateQuestion($group, $type, $caption, $data, $questionId, $question);
        $this->templateQuestions->persist($newQuestion);

        if (!empty($question)) {
            $this->templateQuestions->remove($question);
            return false; // question was updated.
        } else {
            return null; // new question was created.
        }
    }

    /**
     * Deletes a template questions group from the given test.
     * @param TemplateTest $test The template test entity from which the group should be deleted
     * @param string $groupId External ID of the group to delete
     * @return bool true if the group was deleted, false if the group did not exist
     */
    public function deleteGroup(TemplateTest $test, string $groupId): bool
    {
        $group = $this->getTemplateGroup($test, $groupId);
        if (!$group) {
            return false;
        }

        $this->templateQuestionsGroups->remove($group);
        $this->templateQuestionsGroups->flush();
        return true;
    }

    /**
     * Deletes a template question from the given group.
     * @param TemplateTest $test The template test from which the question should be deleted
     * @param TemplateQuestionsGroup $group The questions group from which the question should be deleted
     * @param string $questionId External ID of the question to delete
     * @return bool true if the question was deleted, false if the question did not exist
     */
    public function deleteQuestion(TemplateTest $test, TemplateQuestionsGroup $group, string $questionId): bool
    {
        $question = $this->templateQuestions->findOneBy([
            'externalId' => $questionId,
            'test' => $test->getId(),
            'questionsGroup' => $group->getId()
        ]);

        if (!$question) {
            return false;
        }

        $this->templateQuestions->remove($question);
        $this->templateQuestions->flush();
        return true;
    }
}
