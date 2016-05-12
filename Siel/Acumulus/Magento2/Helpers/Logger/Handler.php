<?php
namespace Siel\Acumulus\Magento2\Helpers\Logger;

use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/acumulus.log';
}
