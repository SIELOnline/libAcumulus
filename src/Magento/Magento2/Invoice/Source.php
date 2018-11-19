<?php
namespace Siel\Acumulus\Magento\Magento2\Invoice;

use Magento\Sales\Model\Order\Creditmemo;
use Siel\Acumulus\Api;
use Siel\Acumulus\Magento\Invoice\Source as BaseSource;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;

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
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        $this->source->load($this->id);
    }

    /**
     * Loads a Credit memo source for the set id.
     */
    protected function setSourceCreditNote()
    {
        $this->source = Registry::getInstance()->create('\Magento\Sales\Model\Order\Creditmemo');
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        $this->source->load($this->id);
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
        return $this->source->getState() == Creditmemo::STATE_REFUNDED
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
            /** @var \Magento\Sales\Model\Order\Status\History $statusChange */
            if (!$paymentDate || $this->isPaidStatus($statusChange->getStatus())) {
                $createdAt = substr($statusChange->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
                if (!$paymentDate || $createdAt < $paymentDate) {
                    $paymentDate = $createdAt;
                }
            }
        }
        return $paymentDate;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        return $this->source->getBillingAddress()->getCountryId();
    }
}
