<?php

declare(strict_types=1);

namespace App\Model\Entity;

use InvalidArgumentException;

/**
 * Adds methods for manipulation with localized properties.
 * At present, the implementation uses JSON-encoding of an array [ locale => translation ].
 */
trait LocalizableEntity
{
    /**
     * Return entire localization structure as an associative array.
     * @param string $name of the property that holds localized data
     * @return array|null [ locale => translation ] or null if no value exists
     */
    public function getLocalizedStructure(string $name): ?array
    {
        $value = $this->$name;
        if (!$value) {
            return null;
        }

        $json = json_decode($value, true);
        return $json && is_array($json) ? $json : null;
    }

    /**
     * Retrieves localized value from a string property. If the translation does not exists, if fallbacks to defaults.
     * @param string $name of the property that holds localized data
     * @param string $locale identifier ('en', 'cs', ...)
     * @param bool $strict if true, only exact locale match is returned (null otherwise)
     * @return string|null (null, if no value exists)
     */
    public function getLocalizedProperty(string $name, string $locale, bool $strict = false): ?string
    {
        $json = $this->getLocalizedStructure($name);
        if (!$json) {
            return null;
        }

        if (array_key_exists($locale, $json)) {
            return $json[$locale]; // exact match
        }
        if ($strict) {
            return null;
        }
        if (array_key_exists('en', $json)) {
            return $json['en']; // fallback to English
        }
        return reset($json); // fallback to the first translation
    }

    /**
     * Sets language-specific value in a localized property.
     * @param string $name of the property that holds localized data
     * @param string $locale identifier ('en', 'cs', ...)
     * @param string $value associated with given locale
     */
    public function setLocalizedProperty(string $name, string $locale, string $value): void
    {
        $json = json_decode($this->$name ?? '', true);
        if (!is_array($json)) {
            $json = [];
        }
        $json[$locale] = $value;
        $this->$name = json_encode($json);
    }

    /**
     * Overwrite all translations of a localized property.
     * @param string $name of the property that holds localized data
     * @param array $translations [ locale => translation ]
     */
    public function overwriteLocalizedProperty(string $name, array $translations): void
    {
        foreach ($translations as $locale => $value) {
            if (!is_string($locale) || !in_array($locale, ['en', 'cs']) || !is_string($value)) {
                throw new InvalidArgumentException(
                    "Translations must be an array of [ locale => string ], where locale is 'en' or 'cs'"
                );
            }
        }

        $this->$name = json_encode($translations);
    }
}
