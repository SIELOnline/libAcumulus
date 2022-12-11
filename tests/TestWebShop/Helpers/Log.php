<?php
namespace Siel\Acumulus\Tests\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the TestWebShop log.
 *
 * Most overrides log to an Acumulus specific log file. If TestWebShop
 * supports so as well, prefer that. Otherwise, you may want to add 'Acumulus'
 * (and the library version) to the message to log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     */
    protected function write(string $message, int $severity)
    {
        $message = sprintf('%s Acumulus %s: %s - %s', date('Y-m-d H:i:s'), $this->getLibraryVersion(), $this->getSeverityString($severity), $message);
        file_put_contents(__DIR__ . '/../../../../logs/test.log', $message . "\n", FILE_APPEND);
    }
}
