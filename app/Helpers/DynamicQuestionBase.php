<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 *
 */
class DynamicQuestionBase
{
    /**
     * The actual code of the main routine that generates the question.
     * @var string
     */
    private $code;

    /**
     * @var QuestionFactory
     */
    private $questionFactory;

    /**
     * Localized text of the question (which can be modified/created by the generator).
     * @var array
     */
    private $text = [ 'en' => '', 'cs' => '' ];

    /**
     * Type of the generated question (must be specified by the generator).
     * @var string|null
     */
    private $type = null;

    /**
     * Instance of the generated question (data). Created once the type is determined.
     * @var IQuestion|null
     */
    private $question = null;

    /**
     * @param string $code to be executed for generating the question
     * @param QuestionFactory $questionFactory (injection)
     */
    public function __construct(string $code, QuestionFactory $questionFactory)
    {
        $this->code = $code;
        $this->questionFactory = $questionFactory;
    }

    /*
     * Getters
     */

    public function getCode(): string
    {
        return $this->code;
    }

    public function getText(): array
    {
        return $this->text;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getQuestion(): ?IQuestion
    {
        return $this->question;
    }

    /*
     * API for the generator
     */

    /**
     * Initialize the dynamic question by setting the type and creating an instance of question data object.
     * @param string $type identifier
     * @return IQuestion just created
     */
    public function init(string $type): IQuestion
    {
        $this->type = $type;
        $this->question = $this->questionFactory->create($type);
        return $this->question;
    }

    /**
     * Set the text for all given locales.
     * @param string $text to be set (overrides current text)
     * @param array|string $locales identifier or list of identifiers
     */
    public function setText(string $text, $locales = [ 'en', 'cs' ]): void
    {
        if (!is_array($locales)) {
            $locales = [ $locales ];
        }

        foreach ($locales as $locale) {
            if (!array_key_exists($locale, $this->text)) {
                throw new QuestionException("Generator tried to set text of unknown locale '$locale'.");
            }

            $this->text[$locale] = $text;
        }
    }

    /**
     * Append a text fragment to all given locales.
     * @param string $text to be set (overrides current text)
     * @param array|string $locales identifier or list of identifiers
     */
    public function appendText(string $text, $locales = [ 'en', 'cs' ]): void
    {
        if (!is_array($locales)) {
            $locales = [ $locales ];
        }

        foreach ($locales as $locale) {
            if (!array_key_exists($locale, $this->text)) {
                throw new QuestionException("Generator tried to set text of unknown locale '$locale'.");
            }

            $this->text[$locale] .= $text;
        }
    }

    /**
     * Replace string fragment(s) for all given locales.
     * @param string|array $search
     * @param string|array $replace
     * @param array|string $locales identifier or list of identifiers
     * @param bool $preg if true, regex replacement (preg_replace) is used, otherwose str_replace()
     */
    public function replaceText($search, $replace, $locales = [ 'en', 'cs' ], bool $preg = false): void
    {
        if (!is_array($locales)) {
            $locales = [ $locales ];
        }

        foreach ($locales as $locale) {
            if (!array_key_exists($locale, $this->text)) {
                throw new QuestionException("Generator tried to set text of unknown locale '$locale'.");
            }

            $this->text[$locale] = $preg
                ? preg_replace($search, $replace, $this->text[$locale])
                : str_replace($search, $replace, $this->text[$locale]);
        }
    }
}
