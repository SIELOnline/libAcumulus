<?php
namespace Siel\Acumulus\Helpers;

/**
 * @class Requirements
 */
class Requirements {

  /**
   * Checks if the requirements for the environment are met.
   *
   * @return array
   *   A possibly empty array with messages regarding missing requirements.
   */
  static public function check() {
    $result = array();

    // PHP 5.3 is a requirement as well because we use namespaces. But as the
    // parser will already have failed fatally before we get here, it makes no
    // sense to check here.
    if (!extension_loaded('curl')) {
      $result[] = 'The CURL PHP extension needs to be activated on your server for the Acumulus module to work.';
    }
    if (!extension_loaded('simplexml')) {
      $result[] = 'The SimpleXML extension needs to be activated on your server for the Acumulus module to be able to work with the XML format.';
    }
    if (!extension_loaded('dom')) {
      $result[] = 'The DOM PHP extension needs to be activated on your server for the Acumulus mpdule to work.';
    }

    return $result;
  }

}
