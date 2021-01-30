<?php
namespace Siel\Acumulus\Magento\Magento2\Helpers;

use Magento\Framework\Filesystem\Driver\File;
use Monolog\Logger;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Magento\Magento2\Helpers\Logger\Handler;
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
            $this->logger = new Logger('acumulus', [new Handler(new File())]);
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
     *   One of the Severity::... constants.
     *
     * @return int
     *   The Magento equivalent of the severity.
     */
    protected function getMagentoSeverity($severity)
    {
        switch ($severity) {
            case Severity::Error:
                return Logger::ERROR;
            case Severity::Warning:
                return Logger::WARNING;
            case Severity::Notice:
                return Logger::NOTICE;
            case Severity::Info:
                return Logger::INFO;
            case Severity::Log:
            default:
                return Logger::DEBUG;
        }
    }
}
