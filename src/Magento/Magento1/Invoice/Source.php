<?php
namespace Siel\Acumulus\Magento\Magento1\Invoice;

use Mage;
use Siel\Acumulus\Api;
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

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        return $this->source->getBillingAddress()->getCountry();
    }

    /**
     * Returns whether the credit memo has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusCreditNote()
    {
        return $this->source->getState() == \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * Returns the payment date for the order.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
     */
    protected function getPaymentDateOrder()
    {
        // Take date of last payment as payment date.
        $paymentDate = null;
        foreach ($this->source->getStatusHistoryCollection() as $statusChange) {
            /** @var \Mage_Sales_Model_Order_Status_History $statusChange */
            if (!$paymentDate || $this->isPaidStatus($statusChange->getStatus())) {
                $createdAt = substr($statusChange->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
                if (!$paymentDate || $createdAt < $paymentDate) {
                    $paymentDate = $createdAt;
                }
            }
        }
        return $paymentDate;
    }
}
