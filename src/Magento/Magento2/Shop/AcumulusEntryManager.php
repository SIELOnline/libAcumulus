<?php
namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements Magento 2 specific methods for the acumulus entry model class.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{

    /**
     * AcumulusEntryManager constructor.
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        parent::__construct($log);
        $this->model = Registry::getInstance()->get('Siel\AcumulusMa2\Model\Entry');
    }
}
