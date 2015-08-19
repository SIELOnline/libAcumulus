<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use \Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the WP log.
 */
class Log extends BaseLog {

  /**
   * {@inheritdoc}
   *
   * This override checks for WP_DEBUG first.
   */
  protected function write($message, $severity) {
    if (WP_DEBUG) {
      parent::write($message, $severity);
    }
  }

}
