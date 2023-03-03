<?php

declare(strict_types=1);

namespace Siel\Acumulus\MyWebShop\Helpers;

use AbstractLogger;
use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the MyWebShop log.
 *
 * Most overrides log to an Acumulus specific log file. If MyWebShop
 * supports so as well, prefer that. Otherwise, you may want to add 'Acumulus'
 * (and the library version) to the message to log.
 */
class Log extends BaseLog
{
    protected AbstractLogger $logger;

    protected function write(string $message, int $severity): void
    {
        // @todo: adapt to MyWebShop's way of logging.
        // @todo: If you do not log to a separate Acumulus log file, you may want to add 'Acumulus' (and the library version) to the message to log.
        $logger = $this->getLogger();
        $logger->log($message, $this->getMyWebShopSeverity($severity));
    }

    /**
     * Returns the MyWebShop equivalent of the severity.
     *
     * @param int $severity
     *   One of the Severity::... constants.
     *
     * @return int
     *   The MyWebShop equivalent of the severity.
     */
    protected function getMyWebShopSeverity(int $severity): int
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

    /**
     * Returns the MyWebShop specific logger.
     *
     * @return \AbstractLogger
     *
     */
    protected function getLogger()
    {
        if (!isset($this->logger)) {
            // @todo: Instantiate a web shop specific log object that logs to a separate Acumulus log file.
            $this->logger = new FileLogger(AbstractLogger::DEBUG);
            $this->logger->setFilename(_ROOT_DIR_ . '/'. $logDirectory . '/acumulus.log');
        }
        return $this->logger;
    }
}
