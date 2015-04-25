<?php
namespace Siel\Acumulus\Helpers;

/**
 * TranslatorInterface defines an interface to retrieve language dependent texts.
 * Most web shops offer their own language handling, but to prevent redoing all
 * the translations in the web shop specific way, a simple generic way is
 * defined here and in the base translator class.
 *
 * @package Siel\Acumulus
 */
interface TranslatorInterface {

  /**
   * Returns the string in the current language for the given key.
   *
   * @param string $key
   *   The key to look up.

   *
   * @return string
   *   Return in order of being available:
   *   - The string in the current language for the given key.
   *   - The string in dutch for the given key.
   *   - The key itself.
   */
  public function get($key);

  /**
   * Instructs this translator to use this collection of translations.
   *
   * @param \Siel\Acumulus\Helpers\TranslationCollection $translationCollection
   *  The translations to use.
   */
  public function add(TranslationCollection $translationCollection);

}
