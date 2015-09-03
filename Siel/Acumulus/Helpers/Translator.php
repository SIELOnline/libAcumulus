<?php
namespace Siel\Acumulus\Helpers;

/**
 * Translator provides a simple way of translating texts.
 */
class Translator implements TranslatorInterface {

  /** @var string */
  protected $language;

  /** @var array */
  protected $translations;

  /**
   * @param string $language
   *   The 2 character language code.
   */
  public function __construct($language) {
    $this->language = $language;
    $this->translations = array();
  }

  /**
   * Instructs this translator to use the translations.
   *
   * @param \Siel\Acumulus\Helpers\TranslationCollection $translationCollection
   *  The translations to use
   */
  public function add(TranslationCollection $translationCollection) {
    $this->translations = array_merge($this->translations, $translationCollection->get($this->getLanguage()));
  }

  /**
   * @inheritdoc
   */
  public function getLanguage() {
    return $this->language;
  }

  /**
   * @inheritdoc
   */
  public function get($key) {
    return (isset($this->translations[$key]) ? $this->translations[$key] : $key);
  }

}
