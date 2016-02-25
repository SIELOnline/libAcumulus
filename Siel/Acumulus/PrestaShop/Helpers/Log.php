<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use PrestaShopLogger;
use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the PrestaShop log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override uses the PrestaShopLogger.
     */
    protected function write($message, $severity)
    {
        PrestaShopLogger::addLog($message, $this->getPrestaShopSeverity($severity));
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
                return 3;
            case Log::Warning:
                return 2;
            case Log::Notice:
                return 1;
            case Log::Debug:
            default:
                return 1;
        }
    }
}
