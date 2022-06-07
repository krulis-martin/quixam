<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\Questions\BaseQuestion;
use Nette\Schema\Processor;
use Exception;

/**
 * Base class for the dynamic question. It wraps the parts hidden from the generator code
 * and provides API the generator can use.
 * The derived class only implements the generate method where the custom code is executed.
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
     * @param array $data deserialized json containing code and optionally the initial text
     * @param QuestionFactory $questionFactory (injection)
     */
    public function __construct(array $data, QuestionFactory $questionFactory)
    {
        if (empty($data['code']) || !is_string($data['code'])) {
            throw new QuestionException("Dynamic question does not contain valid code for the generator.");
        }

        $this->code = $data['code'];
        $this->questionFactory = $questionFactory;

        if (!empty($data['text'])) {
            $processor = new Processor();
            $processor->process(BaseQuestion::schemaOfLocaizedText(), $data['text']); // throws ValidationException

            if (is_array($data['text'])) {
                foreach ($data['text'] as $locale => $text) {
                    $this->setText($text, $locale);
                }
            } else {
                $this->setText($data['text']);
            }
        }
    }

    /**
     * Use AST-parser to validate the code does not contain anything malicous.
     * @param string $code to be verified
     * @return array|bool with error strings or bool (true if the code is ok)
     */
    public static function validateCode(string $code)
    {

        // TODO AST check of the code

        return true;
    }

    /**
     * Internal generator method that is overloaded by the derived class.
     * This method is intentionally in a derived class so private things are kept in the base class.
     */
    protected function generateInternal(): void
    {
        throw new Exception("Not implemented.");
    }

    /**
     * The main routine, which generate the dynamic question.
     */
    public function generate(int $seed): void
    {
        Random::setSeed($seed);

        $this->generateInternal();

        if (!$this->getType() || !$this->getQuestion()) {
            throw new QuestionException("The generator did not initialize the question.");
        }

        // the text must be copied from internal buffer to the constructed question at the end
        $question = $this->getQuestion();
        if ($question instanceof \App\Helpers\Questions\BaseQuestion) {
            $question->setText($this->getText());
        }
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
    protected function init(string $type): IQuestion
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
    protected function setText(string $text, $locales = [ 'en', 'cs' ]): void
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
    protected function appendText(string $text, $locales = [ 'en', 'cs' ]): void
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
    protected function replaceText($search, $replace, $locales = [ 'en', 'cs' ], bool $preg = false): void
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

    /**
     * Random wrapper that relies on pre-initialized seed.
     * @param int $min minimal value
     * @param int|null $max maximal value (inclusive), if null, default max is used
     * @return int|false random value, false if max < min
     */
    protected function random(int $min = 0, $max = null)
    {
        if ($max === null) {
            $max = mt_getrandmax();
        }
        return mt_rand($min, $max);
    }
}
