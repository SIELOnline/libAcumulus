<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;
use WC_Logger;

/**
 * Extends the base log class to log any library logging to the WP log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override logs to the WooCommerce logger facility.
     */
    protected function write($message, $severity)
    {
        $logger = new WC_Logger();
        $message = sprintf('%s - %s', $this->getSeverityString($severity), $message);
        $logger->add('acumulus', $message);
    }
}
