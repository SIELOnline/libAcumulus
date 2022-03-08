<?php
namespace Siel\Acumulus\Magento\Helpers\Logger;

use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    /**
     * @var string
     *   File name.
     */
    protected $fileName = '/var/log/acumulus.log';
}
