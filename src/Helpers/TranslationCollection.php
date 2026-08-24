<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Represents a collection of texts translated into 1 or more languages.
 *
 * This abstract base class defines the way to get access to a set of
 * translations that should be defined in extending classes.
 *
 * See {@see Translator} for more information about the translation system as
 * used in this library.
 */
abstract class TranslationCollection
{
    /**
     * Returns a set of translations for the given language, completed with
     * Dutch translations if no translation for the given language for some key
     * was defined.
     *
     * Module (Shop) specific translations, which are rare and are normally stored in the
     * class {@see ModuleSpecificTranslations}, can have "base translations". These are to
     * be added without overwriting shop specific translations, thus using the + array
     * operator.
     *
     * @return string[]
     *   A keyed array with translations.
     *
     * @noinspection PhpVariableVariableInspection
     */
    public function get(string $language): array
    {
        $baseLanguage = 'base' . ucfirst($language);
        $result = $this->$language ?? [];
        if ($language !== 'nl') {
            $result += $this->nl ?? [];
        }
        $result += $this->$baseLanguage ?? [];
        if ($language !== 'nl') {
            $result += $this->baseNl ?? [];
        }
        return $result;
    }
}
