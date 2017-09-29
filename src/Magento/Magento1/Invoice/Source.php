<?php
namespace Siel\Acumulus\Magento\Magento1\Invoice;

use Mage;
use Siel\Acumulus\Magento\Invoice\Source as BaseSource;

/**
 * Wraps a Magento 1 order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Creditmemo */
    protected $source;

    /**
     * Loads an Order source for the set id.
     */
    protected function setSourceOrder()
    {
        $this->source = Mage::getModel('sales/order');
        $this->source->load($this->id);
    }

    /**
     * Loads a Credit memo source for the set id.
     */
    protected function setSourceCreditNote()
    {
        $this->source = Mage::getModel('sales/order_creditmemo');
        $this->source->load($this->id);
    }
}
