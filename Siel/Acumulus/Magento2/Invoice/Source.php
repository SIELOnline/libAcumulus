<?php
namespace Siel\Acumulus\Magento2\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Magento2\Helpers\Registry;

/**
 * Wraps a Magento 2 order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo */
    protected $source;

    /**
     * Loads an Order source for the set id.
     */
    protected function setSourceOrder()
    {
        $this->source = Registry::getInstance()->create('\Magento\Sales\Model\Order');
        $this->source->load($this->id);
    }

    /**
     * Loads a Credit memo source for the set id.
     */
    protected function setSourceCreditNote()
    {
        $this->source = Registry::getInstance()->create('\Magento\Sales\Model\Order\Creditmemo');
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
     * Returns the status of this credit note.
     *
     * @return int
     *   1 of:
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN        = 1;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED    = 2;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED    = 3;
     */
    protected function getStatusCreditNote()
    {
        return $this->source->getState();
    }
}
