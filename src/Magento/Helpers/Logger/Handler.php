<?php
/**
 * @noinspection LongInheritanceChainInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;

/**
 * This Handler override defines the actual log file location and name.
 */
class Handler extends Base
{
    public function __construct(DriverInterface $filesystem)
    {
        parent::__construct($filesystem, null, 'var/log/acumulus.log');
    }
}
