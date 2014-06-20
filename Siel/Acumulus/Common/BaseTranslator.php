<?php

/**
 * @file Contains Siel\Acumulus\Common\BaseTranslator
 */

namespace Siel\Acumulus\Common;


/**
 * Class BaseTranslator
 */
class BaseTranslator implements TranslatorInterface {

  /** @var string */
  protected $language;

  /** @var array */
  protected $data;

  /**
   * @inheritdoc
   */
  public function __construct($language) {
    $this->language = $language;
    $this->data = array();
    if ($this->language != 'nl') {
      $this->load('nl');
    }
    $this->load($this->language);
  }

  protected function load($language) {
    $file = dirname(__FILE__) . '/Language/' . $language . '.php';
    if (file_exists($file)) {
      $_ = array();
      require($file);
      $this->data = array_merge($this->data, $_);
    }
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
    return (isset($this->data[$key]) ? $this->data[$key] : $key);
  }
}