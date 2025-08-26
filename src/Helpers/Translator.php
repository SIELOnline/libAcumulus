<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Translator provides a simple way of providing translated texts.
 *
 * Most web shops offer their own language handling, but to prevent repeating all the
 * translations in the webshop-specific way, this library defines its own way to get
 * translated texts.
 *
 * How the translation system works:
 * - A single Translator object will be created by the {@see Container} and injected into
 *   every object that needs access to translated texts.
 * - The current language of the request should be passed to the constructor and will be
 *   used as language to get the translations for.
 * - This single Translator object should be fed with translations by adding 1 or more
 *   {@see TranslationCollection} objects.
 * - If multiple {@see TranslationCollection} objects are added, the latter will normally
 *   not overwrite already existing translations. This allows defining web shop specific
 *   vocabulary and add it directly upon instantiating the {@see Container}, thus before
 *   the request is handed off to the library.
 * - However, in some cases the same keys may be used by sibling classes, think of forms
 *   and mails, for information that should be presented on both but may differ slightly
 *   per class. In these cases overwriting is justified as these texts are typically
 *   loaded at instantiation time, not at the initialization of the library.
 *
 * How to define translations:
 * - Create a class that extends {@see TranslationCollection}. Create a class per
 *   logically grouped set of features. You should typically create a collection per
 *   form/page, for mailing, invoice processing, etc.
 * - Add properties per language, named after the 2-character code for that language.
 * - This property should contain a keyed array of strings, the keys being the keys used
 *   in the calling code when a translated text is needed, and the values being the
 *   translated text.
 * - If a text needs to be parameterised, use ordered arguments, like %1$s, as the order
 *   may differ between languages.
 * - Typically, this library is translated into Dutch and English, Dutch being the main
 *   language.
 *
 * How to use translations:
 * - If you need to translate texts in a given class, have the Translator Object injected
 *   into it via the constructor. If you are overriding a base class, most of the time,
 *   the base class will already have done so. If not, ask for a change in the library.
 * - Normally, when the Translator object is injected into a class, also a wrapper method
 *   t() is defined to get a translation.
 * - If a text is parameterised, use {@see sprintf()}, with the translated text as 1st
 *   argument.
 */
class Translator
{
    protected string $language;
    protected array $translations;

    /**
     * @param string $language
     *   The 2-character language code.
     */
    public function __construct(string $language)
    {
        $this->language = $language;
        $this->translations = [];
    }

    /**
     * Adds a collection of translations to this translator.
     *
     * @param TranslationCollection $translationCollection
     *   A possibly multilingual set of translations. The translations for the current
     *   language are added.
     * @param bool $overwrite
     *   Whether to overwrite existing translations or not, the default is false.
     */
    public function add(TranslationCollection $translationCollection, bool $overwrite = false): void
    {
        $this->translations = $overwrite
            ? $translationCollection->get($this->getLanguage()) + $this->translations
            : $this->translations + $translationCollection->get($this->getLanguage());
    }

    /**
     * Returns the current (2-character) language code.
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Returns the string in the current language for the given key.
     *
     * @param string $key
     *   The key to look up.
     *
     * @return string
     *   Return in order of being available:
     *   - The string in the current language for the given key.
     *   - The string in Dutch for the given key.
     *   - The key itself.
     */
    public function get(string $key): string
    {
        return ($this->translations[$key] ?? $key);
    }
}
