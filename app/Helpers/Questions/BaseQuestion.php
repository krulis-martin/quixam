<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\IQuestion;
use App\Helpers\QuestionException;
use App\Helpers\Random;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\SmartObject;
use Exception;

/**
 * Base question handles localized question texts in the same way for all questions.
 */
abstract class BaseQuestion implements IQuestion
{
    use SmartObject;

    /** @var array|null */
    private $text = null;

    /**
     * Return nette schema specification for localized text entity.
     */
    public static function schemaOfLocaizedText()
    {
        return Expect::anyOf(
            Expect::string(),
            Expect::structure([
                'en' => Expect::string(),
                'cs' => Expect::string(),
            ])->skipDefaults()->castTo('array')
        );
    }

    /**
     * Internal method for schema validation/normalization.
     * @param mixed $schema whatever can be accepted as nette schema construct
     * @param mixed $data to be verified and normalized
     * @return mixed normalized data structure (if it passes valiadtion)
     */
    public static function normalize($schema, $data)
    {
        static $processor = null;
        if (!$processor) {
            $processor = new Processor();
        }
        return $processor->process($schema, $data);
    }

    /**
     * Internal function for loading and validtaing localized text.
     * @param array $json deserialized question/template structure to be loaded
     */
    private function loadText(array $json, $errorPrefix)
    {
        if (!array_key_exists('text', $json)) {
            throw new QuestionException("$errorPrefix, item 'text' is missing.");
        }

        try {
            $this->text = self::normalize(self::schemaOfLocaizedText(), $json['text']);
            if (!$this->text) {
                throw new QuestionException("$errorPrefix, item 'text' has no localizations.");
            }
        } catch (Exception $e) {
            throw new QuestionException("$errorPrefix, item 'text' does not have a valid format.", $e);
        }
    }

    /**
     * Retrieves localized value from an internal property. The property must be a string or an array of strings,
     * where the keys are locales. If given locale is not found, fallback sequence is attempted.
     * @param string $value of the property that holds localized data
     * @param string $locale identifier ('en', 'cs', ...)
     * @param bool $strict if true, only exact locale match is returned (null otherwise)
     *                     (this applies only on array properties; string properties match all locales)
     * @return string|null (null, if the translation does not exists)
     */
    public static function getLocalizedText($value, string $locale, bool $strict = false): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = (array)$value;
        }

        if (!$value || !is_array($value)) {
            return null;
        }

        if (array_key_exists($locale, $value)) {
            return $value[$locale]; // exact match
        }
        if ($strict) {
            return null;
        }
        if (array_key_exists('en', $value)) {
            return $value['en']; // fallback to English
        }

        // fallback to the first translation
        $key = array_key_first($value);
        return $key !== null ? $value[$key] : null; /** @phpstan-ignore-line */
    }

    /**
     * Set text manually. This is used when the question is being constructred (e.g., in a generator).
     * @param string|array $text
     */
    public function setText($text): void
    {
        $this->text = self::normalize(self::schemaOfLocaizedText(), $text);
        if (!$this->text) {
            throw new QuestionException("Invalid text format (should be a string or an array of localized strings).");
        }
    }

    /*
     * Implementing the interface
     */

    public function instantiate($templateJson, int $seed): void
    {
        $this->loadText($templateJson, 'Invalid question template');
        Random::setSeed($seed);
    }

    public function load($json): void
    {
        $this->loadText($json, 'Corrupted question data');
    }

    public function jsonSerialize(): mixed
    {
        return [ 'text' => $this->text ];
    }

    public function getText(string $locale): string
    {
        return self::getLocalizedText($this->text, $locale);
    }
}
