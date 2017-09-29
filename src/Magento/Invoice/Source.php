<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a Magento order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Creditmemo|\Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo */
    protected $source;

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
     * {@inheritdoc}
     */
    public function getDate()
    {
        // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
        return substr($this->source->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
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
     *   In Magento 1:
     *   Mage_Sales_Model_Order_Creditmemo::STATE_OPEN         = 1;
     *   Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED     = 2;
     *   Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED     = 3;
     *   or in Magento 2:
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN     = 1;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED = 2;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED = 3;

     */
    protected function getStatusCreditNote()
    {
        return $this->source->getState();
    }

    /**
     * {@inheritdoc}
     */
    protected function setInvoice()
    {
        parent::setInvoice();
        if ($this->getType() === Source::Order) {
            /** @var \Mage_Core_Model_Resource_Db_Collection_Abstract|\Magento\Sales\Model\ResourceModel\Order\Invoice\Collection $shopInvoices */
            $shopInvoices = $this->source->getInvoiceCollection();
            if (count($shopInvoices) > 0) {
                $this->invoice = $shopInvoices->getFirstItem();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReferenceOrder()
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getInvoice() !== null ? $this->getInvoice()->getIncrementId() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDateOrder()
    {
        return $this->getInvoice() !== null ? substr($this->getInvoice()->getCreatedAt(), 0, strlen('2000-01-01')) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getOriginalOrder()
    {
        return new Source(Source::Order, $this->source->getOrder());
    }
}
