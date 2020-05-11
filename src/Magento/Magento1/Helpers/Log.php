<?php
namespace Siel\Acumulus\Magento\Magento1\Helpers;

use Mage;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;
use Zend_Log;

/**
 * Extends the base log class to log any library logging to the Magento log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override uses Mage::log().
     */
    protected function write($message, $severity)
    {
        $message = sprintf('Acumulus: %s', $message);
        Mage::log($message, $this->getMagentoSeverity($severity));
    }

    /**
     * Returns the Magento equivalent of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return int
     *   The Magento equivalent of the severity.
     */
    protected function getMagentoSeverity($severity)
    {
        switch ($severity) {
            case Severity::Error:
                return Zend_Log::ERR;
            case Severity::Warning:
                return Zend_Log::WARN;
            case Severity::Notice:
                return Zend_Log::NOTICE;
            case Severity::Info:
                return Zend_Log::INFO;
            case Severity::Log:
            default:
                return Zend_Log::DEBUG;
        }
    }
}
