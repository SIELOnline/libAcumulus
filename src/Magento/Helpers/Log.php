<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Magento\Framework\Filesystem\Driver\File;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Magento\Helpers\Logger\Handler;
use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the Magento 2 log.
 */
class Log extends BaseLog
{
    protected LoggerInterface $logger;

    protected function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = new Logger('acumulus', [new Handler(new File())]);
        }
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     *
     * This override uses a PSR3 logger based on MonoLog.
     */
    protected function write(string $message, int $severity): void
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
    protected function getMagentoSeverity(int $severity): int
    {
        return match ($severity) {
            Severity::Exception => Logger::CRITICAL,
            Severity::Error => Logger::ERROR,
            Severity::Warning => Logger::WARNING,
            Severity::Notice => Logger::NOTICE,
            Severity::Info => Logger::INFO,
            default => Logger::DEBUG,
        };
    }
}
