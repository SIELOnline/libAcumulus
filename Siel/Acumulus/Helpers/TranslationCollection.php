<?php
namespace Siel\Acumulus\Helpers;

/**
 * Represents a collection of translated texts.
 *
 * Most web shops offer their own language handling, but to prevent redoing all
 * the translations in the web shop specific way, a simple general way is
 * defined.
 *
 * @package Siel\Acumulus
 */
class TranslationCollection {

  /**
   * Returns a set of translations for the given language, completed with Dutch
   * translations if no translation for the given language for some key was
   * defined.
   *
   * @param string $language
   *
   * @return array
   *   A keyed array with translations.
   */
  public function get($language) {
    $result = array();
    if (isset($this->nl)) {
      $result = array_merge($result, $this->nl);
    }
    if (isset($this->{$language})) {
      $result = array_merge($result, $this->{$language});
    }
    return $result;
  }

}
