<?php
namespace Siel\Acumulus\Magento2\Helpers;

use Magento\Framework\Filesystem\Driver\File;
use Monolog\Logger;
use Siel\Acumulus\Magento2\Helpers\Logger\Handler;
use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the Magento 2 log.
 */
class Log extends BaseLog
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new Logger('acumulus', array(new Handler(new File())));
        }
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     *
     * This override uses a PSR3 logger based on MonoLog.
     */
    protected function write($message, $severity)
    {
        $this->getLogger()->log($this->getMagentoSeverity($severity), $message);
    }

    /**
     * Returns the Magento equivalent of the severity.
     *
     * @param int $severity
     *   One of the constants of the base Log class.
     *
     * @return int
     *   The Magento equivalent of the severity.
     */
    protected function getMagentoSeverity($severity)
    {
        switch ($severity) {
            case Log::Error:
                return Logger::ERROR;
            case Log::Warning:
                return Logger::WARNING;
            case Log::Notice:
                return Logger::NOTICE;
            case Log::Debug:
            default:
                return Logger::DEBUG;
        }
    }
}
