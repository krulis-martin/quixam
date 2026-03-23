<?php

declare(strict_types=1);

namespace Parser;

use RuntimeException;
use JsonSerializable;

/**
 * Localized text with builder API, so the text can be accumulated when parsed from a file.
 */
class LocalizedText implements JsonSerializable
{
    public const LOCALES = ['en', 'cs'];

    private $text = [];

    public function __construct()
    {
        foreach (self::LOCALES as $locale) {
            $this->text[$locale] = [];
        }
    }

    public function append(string $locale, $content)
    {
        if ($locale && !array_key_exists($locale, $this->text)) {
            throw new RuntimeException("Unsupported locale '$locale'.");
        }

        if (!$locale) {
            // if no locale is specified, the text is appended to all...
            foreach (self::LOCALES as $locale) {
                $this->append($locale, $content);
            }
            return;
        }

        if (is_array($content)) {
            foreach ($content as $line) {
                $this->append($locale, $line);
            }
        } elseif (is_string($content)) {
            $this->text[$locale][] = $content;
        } else {
            throw new RuntimeException("Only a string or an array can be appended to localized text buffer.");
        }
    }

    public function getText(string $locale): string
    {
        if (!array_key_exists($locale, $this->text)) {
            throw new RuntimeException("Unsupported locale '$locale'.");
        }

        return trim(join("\n", $this->text[$locale]));
    }

    public function hasLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->text) && $this->text[$locale];
    }

    public function jsonSerialize(): mixed
    {
        $json = [];
        foreach (self::LOCALES as $locale) {
            if ($this->hasLocale($locale)) {
                $json[$locale] = $this->getText($locale);
            }
        }
        return $json;
    }
}
