<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Siel\Acumulus\OpenCart\Helpers\Log as BaseLog;

/**
 * OC4 specific Log object creation.
 */
class Log extends BaseLog
{
    protected function getLog(): \Opencart\System\Library\Log
    {
        return new \Opencart\System\Library\Log($this->filename);
    }
}
