<?php
/**
 * Note: we should not use PHP7 language constructs in this child class. See its
 * parent for more information.
 *
 * The PHP7 language constructs we suppress the warnings for:
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

namespace Siel\Acumulus\PrestaShop\Helpers;

use AbstractLogger;
use FileLogger;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the PrestaShop log.
 */
class Log extends BaseLog
{
    /** @var \AbstractLogger */
    protected $logger = null;

    /**
     * {@inheritdoc}
     *
     * This override uses the PrestaShopLogger.
     */
    protected function write(string $message, int $severity)
    {
        $logger = $this->getLogger();
        $logger->log($message, $this->getPrestaShopSeverity($severity));
    }

    /**
     * Returns the PrestaShop equivalent of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return int
     *   The PrestaShop equivalent of the severity.
     */
    protected function getPrestaShopSeverity($severity)
    {
        switch ($severity) {
            case Severity::Error:
                return AbstractLogger::ERROR;
            case Severity::Warning:
                return AbstractLogger::WARNING;
            case Severity::Notice:
            case Severity::Info:
                return AbstractLogger::INFO;
            case Severity::Log:
            default:
                return AbstractLogger::DEBUG;
        }
    }

    protected function getLogger(): FileLogger
    {
        if ($this->logger === null) {

            if (version_compare(_PS_VERSION_, '1.7.5', '>=')) {
                $logDirectory = 'var/logs';
            } else {
                $logDirectory = 'app/logs';
            }
            $this->logger = new FileLogger(AbstractLogger::DEBUG);
            $this->logger->setFilename(_PS_ROOT_DIR_ . '/'. $logDirectory . '/acumulus.log');
        }
        return $this->logger;
    }
}
