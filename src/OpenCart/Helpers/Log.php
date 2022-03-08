<?php
/**
 * Note: we should not use PHP7 language constructs in this child class. See its
 * parent for more information.
 *
 * The PHP7 language constructs we suppress the warnings for:
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

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
    protected function write($message, $severity)
    {
        $log = new \Log('acumulus.log');
        $message = sprintf('%s - %s', $this->getSeverityString($severity), $message);
        $log->write($message);
    }
}
