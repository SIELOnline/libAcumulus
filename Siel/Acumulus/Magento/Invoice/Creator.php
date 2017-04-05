<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento
 * order or credit memo.
 *
 * @todo: multi currency: use base values (default store currency) or values
 *   without base in their names (selected store currency). Other fields
 *   involved:
 *   - base_currency_code
 *   - store_to_base_rate
 *   - store_to_order_rate
 *   - order_currency_code
 */
abstract class Creator extends BaseCreator
{
    /** @var \Mage_Sales_Model_Order|\Magento\Sales\Model\Order */
    protected $order;

    /** @var \Mage_Sales_Model_Order_Creditmemo|\Magento\Sales\Model\Order\Creditmemo */
    protected $creditNote;

    /** @var \Mage_Core_Model_Resource_Db_Collection_Abstract|\Magento\Sales\Model\ResourceModel\Order\Invoice\Collection */
    protected $shopInvoices;

    /** @var \Mage_Sales_Model_Order_Invoice|\Magento\Sales\Model\Order\Invoice */
    protected $shopInvoice;

    /**
     * {@inheritdoc}
     *
     * This override also initializes Magento specific properties related to the
     * source.
     */
    protected function setInvoiceSource($source)
    {
        parent::setInvoiceSource($source);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                $this->creditNote = null;
                break;
            case Source::CreditNote:
                $this->creditNote = $this->invoiceSource->getSource();
                $this->order = $this->creditNote->getOrder();
                break;
        }
        $this->shopInvoices = $this->order->getInvoiceCollection();
        $this->shopInvoice = count($this->shopInvoices) > 0 ? $this->shopInvoices->getFirstItem() : null;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the internal method name of the chosen payment
     * method.
     */
    protected function getPaymentMethod()
    {
        try {
            return $this->order->getPayment()->getMethod();
        }
        catch (\Exception $e) {}
        return parent::getPaymentMethod();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
     */
    protected function getPaymentStateOrder()
    {
        return Number::isZero($this->order->getBaseTotalDue())
            ? ConfigInterface::PaymentStatus_Paid
            : ConfigInterface::PaymentStatus_Due;
    }

    /**
     * Returns whether the order is in a state that makes it to be considered paid.
     *
     * @param string $status
     *
     * @return bool
     */
    protected function isPaidStatus($status)
    {
        return in_array($status, array('processing', 'closed', 'complete'));
    }

    /**
     * Returns the payment date for the credit memo.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
     */
    protected function getPaymentDateCreditNote()
    {
        return substr($this->creditNote->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getInvoiceTotals()
    {
        $sign = $this->invoiceSource->getType() === Source::CreditNote ? -1.0 : 1.0;
        return array(
            'meta-invoice-amountinc' => $sign * $this->invoiceSource->getSource()->getBaseGrandTotal(),
            'meta-invoice-vatamount' => $sign * $this->invoiceSource->getSource()->getBaseTaxAmount(),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLinesOrder()
    {
        $result = array();
        // Items may be composed, so start with all "visible" items.
        foreach ($this->order->getAllVisibleItems() as $item) {
            $result[] = $this->getItemLineOrder($item);
        }
        return $result;
    }

    /**
     * Returns an item line for 1 main product line.
     *
     * @param $item
     *
     * @return array
     */
    abstract protected function getItemLineOrder($item);

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        $name = $this->order->getShippingDescription();
        if (!empty($name)) {
            return $name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = array();
        if (!Number::isZero($this->invoiceSource->getSource()->getDiscountAmount())) {
            $line = array(
                'itemnumber' => '',
                'product' => $this->getDiscountDescription(),
                'vatrate' => null,
                'meta-vatrate-source' => static::VatRateSource_Strategy,
                'meta-strategy-split' => true,
                'quantity' => 1,
            );
            // Product prices incl. VAT => discount amount is also incl. VAT
            if ($this->productPricesIncludeTax()) {
                $line['unitpriceinc'] = $this->getSign() * $this->invoiceSource->getSource()->getDiscountAmount();
            } else {
                $line['unitprice'] = $this->getSign() * $this->invoiceSource->getSource()->getDiscountAmount();
            }
            $result[] = $line;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This implementation may return a manual line for a credit memo.
     */
    protected function getManualLines()
    {
        $result = array();

        if (isset($this->creditNote) && !Number::isZero($this->creditNote->getAdjustment())) {
            $line = array(
                'product' => $this->t('refund_adjustment'),
                'unitprice' => -$this->creditNote->getAdjustment(),
                'quantity' => 1,
                'vatrate' => 0,
            );
            $result[] = $line;
        }
        return $result;
    }

    /**
     * @return string
     */
    protected function getDiscountDescription()
    {
        if ($this->order->getDiscountDescription()) {
            $description = $this->t('discount_code') . ' ' . $this->order->getDiscountDescription();
        } elseif ($this->order->getCouponCode()) {
            $description = $this->t('discount_code') . ' ' . $this->order->getCouponCode();
        } else {
            $description = $this->t('discount');
        }
        return $description;
    }

    /**
     * Returns if the prices for the products are entered with or without tax.
     *
     * @return bool
     *   Whether the prices for the products are entered with or without tax.
     */
    abstract protected function productPricesIncludeTax();
}
