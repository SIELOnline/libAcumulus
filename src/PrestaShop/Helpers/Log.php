<?php

declare(strict_types=1);

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
    public const LogFolder = _PS_ROOT_DIR_ . '/var/logs';
    public const LogFile = 'acumulus.log';

    protected AbstractLogger $logger;

    /**
     * {@inheritdoc}
     *
     * This override uses the PrestaShopLogger.
     *
     * @noinspection PhpMissingParentCallCommonInspection
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
     *   One of the {@see Severity}::... constants.
     *
     * @return int
     *   The PrestaShop equivalent of the severity.
     */
    protected function getPrestaShopSeverity(int $severity): int
    {
        return match ($severity) {
            Severity::Error => AbstractLogger::ERROR,
            Severity::Warning => AbstractLogger::WARNING,
            Severity::Notice, Severity::Info => AbstractLogger::INFO,
            default => AbstractLogger::DEBUG,
        };
    }

    protected function getLogger(): FileLogger
    {
        if (!isset($this->logger)) {
            $this->logger = new FileLogger(AbstractLogger::DEBUG);
            $this->logger->setFilename(Log::LogFolder . '/'. Log::LogFile);
        }
        return $this->logger;
    }
}
