<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Siel\Acumulus\Magento\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements Magento 1 specific methods for the acumulus entry model class.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /**
     * AcumulusEntryModel constructor.
     */
    public function __construct()
    {
        $this->model = \Mage::getModel('acumulus/entry');
    }
}
