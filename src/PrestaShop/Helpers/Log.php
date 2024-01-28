<?php
/**
 * @noinspection PhpClassConstantAccessedViaChildClassInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use AbstractLogger;
use FileLogger;
use PrestaShop\PrestaShop\Core\Version;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the PrestaShop log.
 */
class Log extends BaseLog
{
    protected AbstractLogger $logger;

    /**
     * {@inheritdoc}
     *
     * This override uses the PrestaShopLogger.
     */
    protected function write(string $message, int $severity): void
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
    protected function getPrestaShopSeverity(int $severity): int
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
        if (!isset($this->logger)) {

            if (version_compare(Version::VERSION, '1.7.5', '>=')) {
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
