<?php

declare(strict_types=1);

namespace App\Helpers;

use Latte\Engine;
use JsonSerializable;

interface IQuestion extends JsonSerializable
{
    /**
     * Load question template data and generates an instance of the question using given random seed.
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
     * Render internal content of the form where the user selectes the answer.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      if not null, it will be used to pre-fill the last selected answer
     * @return string raw HTML fragment which is pasted without excaping into the output
     */
    public function renderFormContent(Engine $latte, string $locale, $answer = null): string;

    /**
     * Render HTML content displayed instead of the form when the results are presented.
     * @param Engine $latte engine for rendering latte templates (separately from the presenters)
     * @param string $locale selected locale
     * @param mixed $answer deserialized json structure sent over by the client
     *                      the answer will be used for rendering (null = this question was not answered)
     * @param bool|null $answerIsCorrect how the answer shold be displayed
     *                                   (null = display it indiferently)
     * @return string raw HTML fragment which is pasted without excaping into the output
     */
    public function renderResultContent(
        Engine $latte,
        string $locale,
        $answer = null,
        ?bool $answerIsCorrect = null
    ): string;

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
     * Check whether given answer is the correct answer for this particular question instance.
     * @param mixed $answer deserialized json structure sent over by the client
     * @return bool true if the answer is a correct one
     */
    public function isAnswerCorrect($answer): bool;
}
