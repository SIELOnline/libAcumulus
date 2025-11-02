<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Log as OpenCartLog;
use Siel\Acumulus\OpenCart\Helpers\Log as BaseLog;

/**
 * OC3 specific Log object creation.
 */
class Log extends BaseLog
{
    protected function getLog(): OpenCartLog
    {
        return new OpenCartLog(Log::Filename);
    }
}
