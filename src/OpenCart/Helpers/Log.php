<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;

/**
 * Extends the base log class to log any library logging to the PrestaShop log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override uses the OpenCart Log class.
     */
    protected function write(string $message, int $severity): void
    {
        $log = new \Log('acumulus.log');
        $message = sprintf('%s - %s', $this->getSeverityString($severity), $message);
        $log->write($message);
    }
}
