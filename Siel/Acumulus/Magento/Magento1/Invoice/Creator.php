<?php
namespace Siel\Acumulus\Magento\Magento1\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Magento\Invoice\Creator as BaseCreator;

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
class Creator extends BaseCreator
{
    /** @var \Mage_Sales_Model_Order */
    protected $order;

    /** @var \Mage_Sales_Model_Order_Creditmemo */
    protected $creditNote;

    /** @var \Mage_Core_Model_Resource_Db_Collection_Abstract */
    protected $shopInvoices;

    /** @var \Mage_Sales_Model_Order_Invoice */
    protected $shopInvoice;

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        $this->propertySources['billingAddress'] = $this->invoiceSource->getSource()->getBillingAddress();
        $this->propertySources['shippingAddress'] = $this->invoiceSource->getSource()->getShippingAddress();
        $this->propertySources['customer'] = \Mage::getModel('customer/customer')->load($this->invoiceSource->getSource()->getCustomerId());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return $this->invoiceSource->getSource()->getBillingAddress()->getCountry();
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
        return $this->creditNote->getState() == \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED
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

    /**
     * {@inheritdoc}
     */
    protected function getItemLinesCreditNote()
    {
        $result = array();
        // Items may be composed, so start with all "visible" items.
        foreach ($this->creditNote->getAllItems() as $item) {
            // Only items for which row total is set, are refunded
            /** @var \Mage_Sales_Model_Order_Creditmemo_Item $item */
            if (!Number::isZero($item->getRowTotal())) {
                $result[] = $this->getItemLineCreditNote($item);
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param \Mage_Sales_Model_Order_Item $item
     *
     * @return array
     */
    protected function getItemLineOrder($item)
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
        $lineVat = (float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount();

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
     * @param \Mage_Sales_Model_Order_Creditmemo_Item $item
     *
     * @return array
     */
    protected function getItemLineCreditNote(\Mage_Sales_Model_Order_Creditmemo_Item $item)
    {
        $result = array();

        $lineVat = -((float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount());
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
        /** @var \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Creditmemo $magentoSource */
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
    protected function productPricesIncludeTax()
    {
        /** @var \Mage_Tax_Model_Config $taxConfig */
        $taxConfig = \Mage::getModel('tax/config');
        return $taxConfig->priceIncludesTax();
    }
}
