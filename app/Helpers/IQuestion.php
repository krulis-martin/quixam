<?php

declare(strict_types=1);

namespace App\Helpers;

use Latte\Engine;
use JsonSerializable;

interface IQuestion extends JsonSerializable
{
    /**
     * Load question template data and generate an instance of the question using given random seed.
     * @param mixed $templateJson deserialized data from a template
     * @param int $seed used to initialize random generators
     */
    public function instantiate($templateJson, int $seed): void;

    /**
     * Loads an instance of a question from json (deserialized) structure.
     * @param mixed $json
     */
    public function load($json): void;

    /**
     * Return localized question specification (in Markdown).
     * @param string $locale
     */
    public function getText(string $locale): string;

    /**
     * Return the number of independently-graded items in the question.
     * Compact questions should return 1. Multi-choice questions should return the number of choices.
     * This is also maximum that can be returned from evaluateAnswer(), which returns number of mistakes.
     */
    public function getItemsCount(): int;

    /**
     * Render internal content of the form where the user selects the answer.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      if not null, it will be used to pre-fill the last selected answer
     * @return string raw HTML fragment which is pasted without escaping into the output
     */
    public function renderFormContent(Engine $latte, string $locale, $answer = null): string;

    /**
     * Render HTML content displayed instead of the form when the results are presented.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      the answer will be used for rendering (null = this question was not answered)
     * @param bool|null $answerIsCorrect how the answer should be displayed
     *                                   (null = display it indifferently)
     * @return string raw HTML fragment which is pasted without escaping into the output
     */
    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?bool $answerIsCorrect = null
    ): string;

    /**
     * Render HTML content displayed when the correct answer is presented to the **teacher**.
     * This method is used for teachers preview and manual grading (to compare real answer with the correct one)
     * and for template preview (to check the question template is working).
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @return string raw HTML fragment which is pasted without escaping into the output
     */
    public function renderCorrectContent(Engine $latte, string $locale): string;

    /**
     * Process data sent over from a form and create an answer structure.
     * @param array $postData everything sent over in POST request
     * @return mixed a structure representing an answer (does not have to be valid, if the post data are corrupted)
     */
    public function processAnswerSubmit(array $postData);

    /**
     * Verify whether the answer is structurally correct.
     * This have nothing to do with the actual semantic correctness of the answer.
     * @param mixed $answer deserialized json structure sent over by the client
     * @return bool true if the structure is correct
     */
    public function isAnswerValid($answer): bool;

    /**
     * DEPRECATED Check whether given answer is the correct answer for this particular question instance.
     * @param mixed $answer deserialized json structure sent over by the client
     * @return bool|null true if the answer is a correct one, null if the answer cannot be evaluated automatically
     *                   (the question is configured for manual grading by the teacher)
     */
    public function isAnswerCorrect($answer): ?bool;

    /**
     * Evaluate the answer and return the number of mistakes (0 = fully correct, 1 = one mistake, etc.).
     * @param mixed $answer deserialized json structure sent over by the client
     * @return int|null [0, N] where N is the number of independently-graded items (returned by getItemsCount());
     *                 null if the answer cannot be evaluated automatically
     *                 (the question is configured for manual grading by the teacher)
     */
    public function evaluateAnswer($answer): ?int;

    /**
     * Return a correct answer in the format, that is accepted by isAnswerValid and isAnswerCorrect.
     * @return mixed
     */
    public function getCorrectAnswer();

    /**
     * Indicates whether the question uses random seed to generate its content.
     * If true, the instance of the question is slightly different for each enrolled user.
     * False means that the question is the same for all users and can be rendered without a seed.
     * @return bool
     */
    public function useRandomSeed(): bool;
}
