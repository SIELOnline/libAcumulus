<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JLog;
use \Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the Joomla log.
 */
class Log extends BaseLog {

  /**
   * {@inheritdoc}
   *
   * This override uses JLog.
   */
  protected function write($message, $severity) {
    jimport('joomla.log.log');
    JLog::add($message, $this->getJoomlaSeverity($severity));
  }

  /**
   * Returns the joomla equivalent of the severity.
   *
   * @param int $severity
   *   One of the constants of the base Log class.
   *
   * @return int
   *   the Joomla equivalent of the severity.
   */
  protected function getJoomlaSeverity($severity) {
    switch ($severity) {
      case Log::Error:
        return JLog::ERROR;
      case Log::Warning:
        return JLog::WARNING;
      case Log::Notice:
        return JLog::NOTICE;
      case Log::Debug:
      default:
        return JLog::DEBUG;
    }
  }

}
