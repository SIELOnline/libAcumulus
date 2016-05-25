<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use AbstractLogger;
use FileLogger;
use Siel\Acumulus\Helpers\Log as BaseLog;

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
    protected function write($message, $severity)
    {
        $logger = $this->getLogger();
        $logger->log($message, $this->getPrestaShopSeverity($severity));
    }

    /**
     * Returns the PrestaShop equivalent of the severity.
     *
     * @param int $severity
     *   One of the constants of the base Log class.
     *
     * @return int
     *   The PrestaShop equivalent of the severity.
     */
    protected function getPrestaShopSeverity($severity)
    {
        switch ($severity) {
            case Log::Error:
                return AbstractLogger::ERROR;
            case Log::Warning:
                return AbstractLogger::WARNING;
            case Log::Notice:
            case Log::Info: // @todo: check this in PS project.
                return AbstractLogger::INFO;
            case Log::Debug:
            default:
                return AbstractLogger::DEBUG;
        }
    }

    protected function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new FileLogger(AbstractLogger::DEBUG);
            $this->logger->setFilename(_PS_ROOT_DIR_ . '/log/' . 'acumulus.log');
        }
        return $this->logger;
    }
}
