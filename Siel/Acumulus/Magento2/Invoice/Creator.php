<?php
namespace Siel\Acumulus\Magento2\Invoice;

use \Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Magento2\Helpers\Registry;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento 2
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
class Creator extends BaseCreator
{
    /** @var \Magento\Sales\Model\Order */
    protected $order;

    /** @var \Magento\Sales\Model\Order\Creditmemo */
    protected $creditNote;

    /** @var \Magento\Sales\Model\ResourceModel\Order\Invoice\Collection */
    protected $shopInvoices;

    /** @var \Magento\Sales\Model\Order\Invoice */
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
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        $this->propertySources['billingAddress'] = $this->invoiceSource->getSource()->getBillingAddress();
        $this->propertySources['customer'] = Registry::getInstance()->create('Magento\Customer\Model\Customer')->load($this->invoiceSource->getSource()->getCustomerId());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return $this->invoiceSource->getSource()->getBillingAddress()->getCountryId();
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceNumber($invoiceNumberSource)
    {
        $result = $this->invoiceSource->getReference();
        if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && $this->invoiceSource->getType() === Source::Order && $this->shopInvoice !== null) {
            $result = $this->shopInvoice->getIncrementId();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceDate($dateToUse)
    {
        // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
        $result = substr($this->invoiceSource->getSource()->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
        // A credit note is to be considered an invoice on its own.
        if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate && $this->invoiceSource->getType() === Source::Order && $this->shopInvoice !== null) {
            $result = substr($this->shopInvoice->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
        }
        return $result;
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
     * Returns whether the credit memo has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
     */
    protected function getPaymentStateCreditNote()
    {
        return $this->creditNote->getState() == Creditmemo::STATE_REFUNDED
            ? ConfigInterface::PaymentStatus_Paid
            : ConfigInterface::PaymentStatus_Due;
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
        foreach ($this->order->getStatusHistoryCollection() as $statusChange) {
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
     * {@inheritdoc}
     */
    protected function getItemLinesCreditNote()
    {
        $result = array();
        // Items may be composed, so start with all "visible" items.
        foreach ($this->creditNote->getAllItems() as $item) {
            // Only items for which row total is set, are refunded
            /** @var CreditmemoItem $item */
            if (!Number::isZero($item->getRowTotal())) {
                $result[] = $this->getItemLineCreditNote($item);
            }
        }
        return $result;
    }

    /**
     * Returns an item line for 1 main product line.
     *
     * @param \Magento\Sales\Model\Order\Item $item
     *
     * @return array
     */
    protected function getItemLineOrder(OrderItem $item)
    {
        $result = array();

        $vatRate = (float) $item->getTaxPercent();
        $productPriceInc = (float) $item->getPriceInclTax();
        // For higher precision of the unit price, we use the prices as entered
        // by the admin.
        $productPriceEx = $this->productPricesIncludeTax() ? (float) $productPriceInc / (100.0 + $vatRate) * 100.0 : (float) $item->getPrice();
        // Tax amount = VAT over discounted product price.
        // Hidden tax amount = VAT over discount.
        // But as discounts get their own lines and the product lines are
        // showing the normal (not discounted) price we add these 2.
        $lineVat = (float) $item->getTaxAmount() + (float) $item->getDiscountTaxCompensationAmount();

        // Simple products (products without children): add as 1 line.
        $this->addIfNotEmpty($result, 'itemnumber', $item->getSku());
        $result += array(
            'product' => $item->getName(),
            'unitprice' => $productPriceEx,
            'unitpriceinc' => $productPriceInc,
            'vatrate' => $vatRate,
            'meta-line-vatamount' => $lineVat,
            'quantity' => $item->getQtyOrdered(),
            'meta-vatrate-source' => static::VatRateSource_Exact,
        );
        if (!Number::isZero($item->getDiscountAmount())) {
            // Store discount on this item to be able to get correct discount
            // lines later on in the completion phase.
            $result['meta-line-discount-amountinc'] = -$item->getDiscountAmount();
        }

        // Add child lines for composed products, a.o. to be able to print a
        // packing slip in Acumulus.
        $childrenItems = $item->getChildrenItems();
        if (!empty($childrenItems)) {
            $result[Creator::Line_Children] = array();
            foreach ($childrenItems as $child) {
                $result[Creator::Line_Children][] = $this->getItemLineOrder($child);
            }
        }

        return $result;
    }

    /**
     * Returns 1 item line for 1 credit line.
     *
     * @param CreditmemoItem $item
     *
     * @return array
     */
    protected function getItemLineCreditNote(CreditmemoItem $item)
    {
        $result = array();

        $lineVat = -((float) $item->getTaxAmount() + (float) $item->getDiscountTaxCompensationAmount());
        $productPriceEx = -((float) $item->getPrice());

        // On a credit note we only have single lines, no compound lines.
        $this->addIfNotEmpty($result, 'itemnumber', $item->getSku());
        $result += array(
            'product' => $item->getName(),
            'unitprice' => $productPriceEx,
            'quantity' => $item->getQty(),
            'meta-line-vatamount' => $lineVat,
        );

        if ($this->productPricesIncludeTax()) {
            $productPriceInc = -((float) $item->getPriceInclTax());
            $result['unitpriceinc'] = $productPriceInc;
        }

        $orderItemId = $item->getOrderItemId();
        if (!empty($orderItemId)) {
            $orderItem = $item->getOrderItem();
            $result += array(
                'vatrate' => $orderItem->getTaxPercent(),
                'meta-vatrate-source' => static::VatRateSource_Exact,
            );
        } else {
            $result += $this->getVatRangeTags($lineVat / $item->getQty(), $productPriceEx, 0.02, 0.02);
            $result['meta-calculated-fields'][] = 'vatamount';
        }

        if (!Number::isZero($item->getDiscountAmount())) {
            // Credit note: discounts are cancelled, thus amount is positive.
            $result['meta-line-discount-amountinc'] = $item->getDiscountAmount();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = array();
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $magentoSource */
        $magentoSource = $this->invoiceSource->getSource();
        // Only add a free shipping line on an order, not on a credit note:
        // free shipping is never refunded...
        if ($this->invoiceSource->getType() === Source::Order || !Number::isZero($magentoSource->getShippingAmount())) {
            $result += array(
                'itemnumber' => '',
                'product' => $this->getShippingMethodName(),
                'quantity' => 1,
            );

            // What do the following methods return:
            // - getShippingAmount():         shipping costs excl VAT excl any discount
            // - getShippingInclTax():        shipping costs incl VAT excl any discount
            // - getShippingTaxAmount():      VAT on shipping costs incl discount
            // - getShippingDiscountAmount(): discount on shipping incl VAT
            /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $magentoSource */
            $magentoSource = $this->invoiceSource->getSource();
            if (!Number::isZero($magentoSource->getShippingAmount())) {
                // We have 2 ways of calculating the vat rate: first one is based on tax
                // amount and normal shipping costs corrected with any discount (as the
                // tax amount is including any discount):
                // $vatRate1 = $magentoSource->getShippingTaxAmount() / ($magentoSource->getShippingInclTax() - $magentoSource->getShippingDiscountAmount() - $magentoSource->getShippingTaxAmount());
                // However, we will use the 2nd way as that seems to be more precise and,
                // thus generally leads to a smaller range:
                // Get range based on normal shipping costs incl and excl VAT.
                $sign = $this->getSign();
                $shippingInc = $sign * $magentoSource->getShippingInclTax();
                $shippingEx = $sign * $magentoSource->getShippingAmount();
                $shippingVat = $shippingInc - $shippingEx;
                $result += array(
                        'unitprice' => $shippingEx,
                        'unitpriceinc' => $shippingInc,
                    ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,
                        0.01);
                $result['meta-calculated-fields'][] = 'vatamount';

                // getShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getShippingDiscountAmount())) {
                    $result['meta-line-discount-amountinc'] = -$sign * $magentoSource->getShippingDiscountAmount();
                } elseif ($this->invoiceSource->getType() === Source::CreditNote
                    && !Number::floatsAreEqual($shippingVat, $magentoSource->getShippingTaxAmount(), 0.02)) {
                    // On credit notes, the shipping discount amount is not stored but can
                    // be deduced via the shipping discount tax amount and the shipping vat
                    // rate. To get a more precise 'meta-line-discount-amountinc', we
                    // compute that in the completor when we have corrected the vatrate.
                    $result['meta-line-discount-vatamount'] = $sign * ($shippingVat - $sign * $magentoSource->getShippingTaxAmount());
                }
            } else {
                // Free shipping should get a "normal" tax rate. We leave that
                // to the completor to determine.
                $result += array(
                    'unitprice' => 0,
                    'vatrate' => null,
                    'meta-vatrate-source' => static::VatRateSource_Completor,
                );
            }
        }
        return $result;
    }

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
    protected function productPricesIncludeTax()
    {
        /** @var \Magento\Tax\Model\Config $taxConfig */
        $taxConfig = Registry::getInstance()->create('Magento\Tax\Model\Config');
        return $taxConfig->priceIncludesTax();
    }
}
