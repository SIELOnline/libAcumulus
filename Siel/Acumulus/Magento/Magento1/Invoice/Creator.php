<?php
namespace Siel\Acumulus\Magento\Magento1\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Magento\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

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
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStateCreditNote()
    {
        return $this->creditNote->getState() == \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED
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

        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);

        // For higher precision of the unit price, we use the price as entered
        // by the admin.
        if ($this->productPricesIncludeTax()) {
            $tag = Meta::UnitPriceInc;
            $productPrice = (float) $item->getPriceInclTax();

        } else {
            $tag = Tag::UnitPrice;
            $productPrice = (float) $item->getPrice();
        }

        // Tax amount = VAT over discounted product price.
        // Hidden tax amount = VAT over discount.
        // Tax percent = VAT % as specified in product settings, for the parent
        // of bundled products this may be 0 and incorrect.
        // But as discounts get their own lines and the product lines are
        // showing the normal (not discounted) price we add these 2.
        $vatRate = (float) $item->getTaxPercent();
        $lineVat = (float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount();

        // Add price and quantity info.
        $result += array(
            $tag => $productPrice,
            Tag::Quantity => $item->getQtyOrdered(),
            Meta::LineVatAmount => $lineVat,
        );

        // Add VAT related info
        $childrenItems = $item->getChildrenItems();
        if (Number::isZero($vatRate) && !empty($childrenItems)) {
            // 0 VAT rate on parent: this is (very very) probably not correct.
            $result += array(
                Tag::VatRate => null,
                Meta::VatRateSource => Creator::VatRateSource_Completor,
                Meta::VatRateLookup => $vatRate,
                Meta::VatRateLookupSource => '$item->getTaxPercent()',
            );
        } else {
            // No 0 VAT or not a parent product: the vat rate is real.
            $result += array(
                Tag::VatRate => $vatRate,
                Meta::VatRateSource => Number::isZero($vatRate) ? Creator::VatRateSource_Exact0 : Creator::VatRateSource_Exact,
            );
        }

        // Add discount related info.
        if (!Number::isZero($item->getDiscountAmount())) {
            // Store discount on this item to be able to get correct discount
            // lines later on in the completion phase.
            $result[Meta::LineDiscountAmountInc] = -$item->getDiscountAmount();
        }

        // Add children lines for composed products.
        if (!empty($childrenItems)) {
            $result[Meta::ChildrenLines] = array();
            foreach ($childrenItems as $child) {
                $result[Meta::ChildrenLines][] = $this->getItemLineOrder($child);
            }
        }

        $this->removePropertySource('item');

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

        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);

        $lineVat = -((float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount());
        $productPriceEx = -((float) $item->getPrice());

        // On a credit note we only have single lines, no compound lines.
        $result += array(
            Tag::UnitPrice => $productPriceEx,
            Tag::Quantity => $item->getQty(),
            Meta::LineVatAmount => $lineVat,
        );

        if ($this->productPricesIncludeTax()) {
            $productPriceInc = -((float) $item->getPriceInclTax());
            $result[Meta::UnitPriceInc] = $productPriceInc;
        }

        $orderItemId = $item->getOrderItemId();
        if (!empty($orderItemId)) {
            $orderItem = $item->getOrderItem();
            $result += array(
                Tag::VatRate => $orderItem->getTaxPercent(),
                Meta::VatRateSource => static::VatRateSource_Exact,
            );
        } else {
            $result += $this->getVatRangeTags($lineVat / $item->getQty(), $productPriceEx, 0.02, 0.02);
            $result[Meta::CalculatedFields][] = Meta::VatAmount;
        }

        if (!Number::isZero($item->getDiscountAmount())) {
            // Credit note: discounts are cancelled, thus amount is positive.
            $result[Meta::LineDiscountAmountInc] = $item->getDiscountAmount();
        }

        $this->removePropertySource('item');

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
                Tag::Product => $this->getShippingMethodName(),
                Tag::Quantity => 1,
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
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                    ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,
                        0.01);
                $result[Meta::CalculatedFields][] = Meta::VatAmount;

                // getShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getShippingDiscountAmount())) {
                    $result[Meta::LineDiscountAmountInc] = -$sign * $magentoSource->getShippingDiscountAmount();
                } elseif ($this->invoiceSource->getType() === Source::CreditNote
                    && !Number::floatsAreEqual($shippingVat, $magentoSource->getShippingTaxAmount(), 0.02)) {
                    // On credit notes, the shipping discount amount is not stored but can
                    // be deduced via the shipping discount tax amount and the shipping vat
                    // rate. To get a more precise Meta::LineDiscountAmountInc, we
                    // compute that in the completor when we have corrected the vatrate.
                    $result[Meta::LineDiscountVatAmount] = $sign * ($shippingVat - $sign * $magentoSource->getShippingTaxAmount());
                }
            } else {
                // Free shipping should get a "normal" tax rate. We leave that
                // to the completor to determine.
                $result += array(
                    Tag::UnitPrice => 0,
                    Tag::VatRate => null,
                    Meta::VatRateSource => static::VatRateSource_Completor,
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
