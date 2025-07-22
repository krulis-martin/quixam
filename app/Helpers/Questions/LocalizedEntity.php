<?php

declare(strict_types=1);

namespace App\Helpers\Questions;

use App\Helpers\QuestionException;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\SmartObject;
use Exception;

/**
 * An entity that holds localized text property and methods for properly setting/getting it.
 * This is intended to be a base class for questions and other items.
 */
class LocalizedEntity
{
    use SmartObject;

    /** @var array|null */
    protected $text = null;

    /**
     * Return nette schema specification for localized text entity.
     */
    public static function schemaOfLocalizedText()
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
    protected function loadText(array $json, $errorPrefix)
    {
        if (!array_key_exists('text', $json)) {
            throw new QuestionException("$errorPrefix, item 'text' is missing.");
        }

        try {
            $this->text = self::normalize(self::schemaOfLocalizedText(), $json['text']);
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
        return $key !== null ? $value[$key] : null;
    }

    /**
     * Set text manually.
     * @param string|array $text
     */
    public function setText($text): void
    {
        $this->text = static::normalize(static::schemaOfLocalizedText(), $text);
        if (!$this->text) {
            throw new QuestionException("Invalid text format (should be a string or an array of localized strings).");
        }
    }

    public function getText(string $locale): string
    {
        return static::getLocalizedText($this->text, $locale);
    }
}
