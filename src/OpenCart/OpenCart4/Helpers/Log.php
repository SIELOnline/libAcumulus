<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Opencart\System\Library\Log as OpenCartLog;
use Siel\Acumulus\OpenCart\Helpers\Log as BaseLog;

/**
 * OC4 specific Log object creation.
 */
class Log extends BaseLog
{
    protected function getLog(): OpenCartLog
    {
        return new OpenCartLog(Log::Filename);
    }
}
