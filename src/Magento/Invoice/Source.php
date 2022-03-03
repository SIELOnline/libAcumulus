<?php
namespace Siel\Acumulus\Magento\Invoice;

use Magento\Sales\Model\Order\Creditmemo;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Meta;

/**
 * Wraps a Magento order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo */
    protected $source;

    /**
     * {@inheritdoc}
     */
    protected function setId()
    {
        $this->id = $this->source->getId();
    }

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
     *
     * This override returns the internal method name of the chosen payment
     * method.
     */
    public function getPaymentMethod()
    {
        try {
            return $this->getOrder()->source->getPayment()->getMethod();
        }
        catch (\Exception $e) {}
        return parent::getPaymentMethod();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusOrder()
    {
        return Number::isZero($this->source->getBaseTotalDue())
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
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
     * Returns whether the order is in a status that makes it considered paid.
     *
     * This method is NOT used to determine the paid status, but is used to
     * determine the paid date by looking for these statuses in the
     * StatusHistoryCollection.
     *
     * @param string $status
     *
     * @return bool
     */
    protected function isPaidStatus($status)
    {
        return in_array($status, ['processing', 'closed', 'complete']);
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
     * Returns the payment date for the credit memo.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
     */
    protected function getPaymentDateCreditNote()
    {
        return substr($this->source->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    public function getCurrency()
    {
        $result = array (
            Meta::Currency => $this->source->getOrderCurrencyCode(),
            Meta::CurrencyRate => (float) $this->source->getBaseToOrderRate(),
            Meta::CurrencyDoConvert => false,
        );
        return $result;

    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getAvailableTotals()
    {
        $sign = $this->getSign();
        return [
            Meta::InvoiceAmountInc => $sign * $this->source->getBaseGrandTotal(),
            Meta::InvoiceVatAmount => $sign * $this->source->getBaseTaxAmount(),
        ];
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
    protected function getShopOrderOrId()
    {
        /** @var \Mage_Sales_Model_Order_Creditmemo|\Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->source;
        return $creditmemo->getOrderId();
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopCreditNotesOrIds()
    {
        /** @var \Mage_Sales_Model_Order|\Magento\Sales\Model\Order $order */
        $order = $this->source;
        return $order->getCreditmemosCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        return $this->source->getBillingAddress()->getCountryId();
    }
}
