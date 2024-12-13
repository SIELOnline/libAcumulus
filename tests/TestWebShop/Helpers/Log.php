<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Log as BaseLog;

use function dirname;
use function sprintf;

/**
 * Extends the base log class to log any library logging to the TestWebShop log.
 *
 * Most overrides log to an Acumulus specific log file. If TestWebShop
 * supports so as well, prefer that. Otherwise, you may want to add 'Acumulus'
 * (and the library version) to the message to log.
 */
class Log extends BaseLog
{
    protected function write(string $message, int $severity): void
    {
        $message = sprintf('%s Acumulus %s: %s - %s', date(Api::Format_TimeStamp), $this->getLibraryVersion(), $this->getSeverityString($severity), $message);
        file_put_contents(dirname(__FILE__, 5) . '/logs/test.log', $message . "\n", FILE_APPEND);
    }
}
