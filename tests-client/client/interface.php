<?php

declare(strict_types=1);

namespace Quixam;

use RuntimeException;

/**
 * High-level interface for the Quixam API client.
 * This interface can be implemented via CLI or REST connections.
 */
interface IApiClient
{
    /**
     * Gets the structure of the test template with given ID. Returns null if the test does not exist.
     * The structure is an array of groups, where each group is an array of questions.
     * @param string $testId External ID of the test template.
     * @return array|null The parsed structure of the test template or null if the test does not exist.
     * @throws RuntimeException if the retrieval or parsing fails.
     */
    public function getTestStructure(string $testId): ?array;

    /**
     * Adds/updates a question group.
     * @param string $testId External ID of the test template.
     * @param string $groupId External ID of the question group.
     * @param int $points Points assigned to the group.
     * @param int $count Number of questions to select from the group.
     * @param int $ordering Ordering of the group within the test template.
     * @throws RuntimeException if the group creation/update fails.
     */
    public function addGroup(string $testId, string $groupId, int $points, int $count, int $ordering): void;

    /**
     * Adds/updates a question.
     * @param string $testId External ID of the test template.
     * @param string $groupId External ID of the question group.
     * @param string $questionId External ID of the question.
     * @param string $type Question type (as defined in the quixam api).
     * @param string $caption_en English translation of the question caption.
     * @param string $caption_cs Czech translation of the question caption (optional).
     * @param array $data Question data as an associative array. The structure depends on the question type.
     * @throws RuntimeException if the question creation/update fails.
     */
    public function addQuestion(
        string $testId,
        string $groupId,
        string $questionId,
        string $type,
        string $caption_en,
        string $caption_cs,
        array $data
    ): void;

    /**
     * Deletes a question group.
     * @param string $testId External ID of the test template.
     * @param string $groupId External ID of the question group.
     * @throws RuntimeException if the deletion fails.
     */
    public function deleteGroup(string $testId, string $groupId): void;

    /**
     * Deletes a question.
     * @param string $testId External ID of the test template.
     * @param string $groupId External ID of the question group.
     * @param string $questionId External ID of the question.
     * @throws RuntimeException if the deletion fails.
     */
    public function deleteQuestion(string $testId, string $groupId, string $questionId): void;
}
