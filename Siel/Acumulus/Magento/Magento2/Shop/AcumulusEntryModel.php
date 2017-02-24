<?php
namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements Magento 2 specific methods for the acumulus entry model class.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /**
     * AcumulusEntryModel constructor.
     */
    public function __construct()
    {
        $this->model = Registry::getInstance()->get('Siel\AcumulusMa2\Model\Entry');
    }
}
