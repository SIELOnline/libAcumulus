<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Siel\Acumulus\OpenCart\Helpers\Log as BaseLog;

/**
 * OC3 specific Log object creation.
 */
class Log extends BaseLog
{
    /**
     * {@inheritDoc}
     */
    protected function getLog(): \Log
    {
        return new \Log($this->filename);
    }
}
