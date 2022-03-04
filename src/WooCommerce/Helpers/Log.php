<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;
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
        (new WC_Logger())->log($this->getWooCommerceSeverity($severity), $message, ['source' => 'acumulus']);
    }

    /**
     * Returns the WooCommerce equivalent of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return string
     *   The WooCommerce equivalent of the severity, @see WC_Logger::Log() for
     *   a list of severities:
     *   - 'emergency': System is unusable.
     *   - 'alert': Action must be taken immediately.
     *   - 'critical': Critical conditions.
     *   - 'error': Error conditions.
     *   - 'warning': Warning conditions.
     *   - 'notice': Normal but significant condition.
     *   - 'info': Informational messages.
     *   - 'debug': Debug-level messages.
     */
    protected function getWooCommerceSeverity(int $severity): string
    {
        switch ($severity) {
            case Severity::Exception:
                return 'critical';
            case Severity::Error:
                return 'error';
            case Severity::Warning:
                return 'warning';
            case Severity::Notice:
                return 'notice';
            case Severity::Info:
                return 'info';
            case Severity::Log:
            default:
                return 'debug';
        }
    }
}
