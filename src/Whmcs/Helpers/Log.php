<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Log as BaseLog;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends the base log class to log any library logging to the WHMCS activity log.
 */
class Log extends BaseLog
{
    /**
     * {@inheritdoc}
     *
     * This override logs to the WHMCS activity log.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function logCompleteMessage(string $message): void
    {
        logActivity($message);
    }
}
