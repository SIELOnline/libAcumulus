<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Magento\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements Magento 1 specific methods for the acumulus entry model class.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->model = \Mage::getModel('acumulus/entry');
        $this->resourceModel = \Mage::getResourceModel('acumulus/entry');
    }
}
