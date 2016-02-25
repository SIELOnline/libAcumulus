<?php
namespace Siel\Acumulus\Magento\Invoice;

use Mage;
use Mage_Sales_Model_Order;
use Mage_Sales_Model_Order_Creditmemo;
use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a Magento order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo */
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

    /**
     * {@inheritdoc}
     */
    protected function setId()
    {
        $this->id = $this->source->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the order reference.
     *
     * @return string
     */
    protected function getReferenceOrder()
    {
        return $this->source->getIncrementId();
    }

    /**
     * Returns the credit note reference.
     *
     * @return string
     */
    protected function getReferenceCreditNote()
    {
        return 'CM' . $this->source->getIncrementId();
    }

    /**
     * Returns the status of this order.
     *
     * @return string
     */
    protected function getStatusOrder()
    {
        return $this->source->getStatus();
    }

    /**
     * Returns the status of this order.
     *
     * @return int
     *   1 of
     *   Mage_Sales_Model_Order_Creditmemo::STATE_OPEN        = 1;
     *   Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED    = 2;
     *   Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED    = 3;
     */
    protected function getStatusCreditNote()
    {
        return $this->source->getState();
    }
}
