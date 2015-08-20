<?php
namespace Siel\Acumulus\Magento\Helpers;

use Mage;
use \Siel\Acumulus\Helpers\Log as BaseLog;
use Zend_Log;

/**
 * Extends the base log class to log any library logging to the Magento log.
 */
class Log extends BaseLog {

  /**
   * {@inheritdoc}
   *
   * This override uses Mage::log().
   */
  protected function write($message, $severity) {
    Mage::log($message, $this->getMagentoSeverity($severity));
  }

  /**
   * Returns the Magento equivalent of the severity.
   *
   * @param int $severity
   *   One of the constants of the base Log class.
   *
   * @return int
   *   The Magento equivalent of the severity.
   */
  protected function getMagentoSeverity($severity) {
    switch ($severity) {
      case Log::Error:
        return Zend_Log::ERR;
      case Log::Warning:
        return Zend_Log::WARN;
      case Log::Notice:
        return Zend_Log::NOTICE;
      case Log::Debug:
      default:
        return Zend_Log::DEBUG;
    }
  }

}
