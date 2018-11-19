<?php
namespace Siel\Acumulus\MyWebShop\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the MyWebShop log.
 *
 * Most overrides log to an Acumulus specific log file. If MyWebshop
 * supports so as well, prefer that. Otherwise, you may want to add 'Acumulus'
 * (and the library version) to the message to log.
 */
class Log extends BaseLog
{
    /** @var \AbstractLogger */
    protected $logger = null;

    /**
     * {@inheritdoc}
     */
    protected function write($message, $severity)
    {
        // @todo: adapt to MyWebshop's way of logging.
        // @todo: If you do not log to a separate Acumulus log file, you may want to add 'Acumulus' (and the library version) to the message to log.
        $logger = $this->getLogger();
        $logger->log($message, $this->getMyWebShopSeverity($severity));
    }

    /**
     * Returns the MyWebShop equivalent of the severity.
     *
     * @param int $severity
     *   One of the constants of the base Log class.
     *
     * @return int
     *   The MyWebShop equivalent of the severity.
     */
    protected function getMyWebShopSeverity($severity)
    {
        switch ($severity) {
            case Log::Error:
                return AbstractLogger::ERROR;
            case Log::Warning:
                return AbstractLogger::WARNING;
            case Log::Notice:
            case Log::Info:
                return AbstractLogger::INFO;
            case Log::Debug:
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
        if ($this->logger === null) {
            // @todo: Instantiate a webshop specific log object that logs to a separate Acumulus log file.
            $this->logger = new FileLogger(AbstractLogger::DEBUG);
            $this->logger->setFilename(_ROOT_DIR_ . '/'. $logDirectory . '/acumulus.log');
        }
        return $this->logger;
    }
}
