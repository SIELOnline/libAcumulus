<?php
/**
 * @file Contains the translator Interface.
 */

namespace Siel\Acumulus;

/**
 * TranslatorInterface defines an interface to retrieve language dependent texts.
 * Most web shops offer their own language handling, but to prevent redoing all
 * the translations in the web shop specific way, a simple general way is
 * defined.
 *
 * @package Siel\Acumulus
 */
interface TranslatorInterface {

  /**
   * @param string $language
   *   The 2 character language code.
   */
  public function __construct($language);

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
}
