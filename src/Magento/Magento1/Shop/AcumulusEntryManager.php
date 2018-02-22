<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Magento\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements Magento 1 specific methods for the acumulus entry model class.
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
        $this->model = \Mage::getModel('acumulus/entry');
    }
}
