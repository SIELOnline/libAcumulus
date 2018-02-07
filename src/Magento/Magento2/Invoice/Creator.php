<?php
namespace Siel\Acumulus\Magento\Magento2\Invoice;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Magento\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento 2
 * order or credit memo.
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
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();

        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $source */
        $source = $this->invoiceSource->getSource();
        if ($source->getBillingAddress() !== null) {
            $this->propertySources['billingAddress'] = $source->getBillingAddress();
        } else {
            $this->propertySources['billingAddress'] = $source->getShippingAddress();
        }
        if ($source->getShippingAddress() !== null) {
            $this->propertySources['shippingAddress'] = $source->getShippingAddress();
        } else {
            $this->propertySources['shippingAddress'] = $source->getBillingAddress();
        }

        $this->propertySources['customer'] = Registry::getInstance()->create('Magento\Customer\Model\Customer')->load($source->getCustomerId());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $source */
        $source = $this->invoiceSource->getSource();
        return $source->getBillingAddress()->getCountryId();
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
        return $this->creditNote->getState() == Creditmemo::STATE_REFUNDED
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
     * {@inheritdoc}
     *
     * @param \Magento\Sales\Model\Order\Item $item
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

        // For higher precision of the unit price, we will recalculate the price
        // ex vat if product prices are entered inc vat by the admin.
        $productPriceEx = (float) $item->getBasePrice();
        $productPriceInc = (float) $item->getBasePriceInclTax();

        // Add price and quantity info.
        $result += array(
            Tag::UnitPrice => $productPriceEx,
            Meta::UnitPriceInc => $productPriceInc,
            Meta::RecalculateUnitPrice => $this->productPricesIncludeTax(),
            Tag::Quantity => $item->getQtyOrdered(),
        );

        // Tax amount = VAT over discounted product price.
        // Hidden tax amount = VAT over discount.
        // Tax percent = VAT % as specified in product settings, for the parent
        // of bundled products this may be 0 and incorrect.
        // But as discounts get their own lines and the product lines are
        // showing the normal (not discounted) price we add these 2.
        $vatRate = (float) $item->getTaxPercent();
        $lineVat = (float) $item->getBaseTaxAmount() + (float) $item->getBaseDiscountTaxCompensationAmount();

        // Add VAT related info.
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
        $result += array(
            Meta::LineVatAmount => $lineVat,
        );

        // Add discount related info.
        if (!Number::isZero($item->getBaseDiscountAmount())) {
            // Store discount on this item to be able to get correct discount
            // lines later on in the completion phase.
            $result[Meta::LineDiscountAmountInc] = -$item->getBaseDiscountAmount();
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
     * @param CreditmemoItem $item
     *
     * @return array
     */
    protected function getItemLineCreditNote(CreditmemoItem $item)
    {
        $result = array();

        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);

        $productPriceEx = -((float) $item->getBasePrice());
        $productPriceInc = -((float) $item->getBasePriceInclTax());
        $lineVat = -((float) $item->getBaseTaxAmount() + (float) $item->getBaseDiscountTaxCompensationAmount());

        // Add price and quantity info.
        $result += array(
            Tag::UnitPrice => $productPriceEx,
            Meta::UnitPriceInc => $productPriceInc,
            Meta::RecalculateUnitPrice => $this->productPricesIncludeTax(),
            Tag::Quantity => $item->getQty(),
            Meta::LineVatAmount => $lineVat,
        );

        // Add VAT related info.
        $orderItemId = $item->getOrderItemId();
        if (!empty($orderItemId)) {
            $orderItem = $item->getOrderItem();
            $result += array(
                Tag::VatRate => $orderItem->getTaxPercent(),
                Meta::VatRateSource => static::VatRateSource_Exact,
            );
        } else {
            $result += $this->getVatRangeTags($lineVat / $item->getQty(), $productPriceEx, 0.02, $this->productPricesIncludeTax() ? 0.02 : 0.01);
            $result[Meta::FieldsCalculated][] = Meta::VatAmount;
        }

        // Add discount related info.
        if (!Number::isZero($item->getDiscountAmount())) {
            // Credit note: discounts are cancelled, thus amount is positive.
            $result[Meta::LineDiscountAmountInc] = $item->getBaseDiscountAmount();
        }

        // On a credit note we only have single lines, no compound lines, thus
        // no children that might have to be added.

        $this->removePropertySource('item');

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
        if ($this->invoiceSource->getType() === Source::Order || !Number::isZero($magentoSource->getBaseShippingAmount())) {
            $result += array(
                Tag::Product => $this->getShippingMethodName(),
                Tag::Quantity => 1,
            );

            // What do the following methods return:
            // - getBaseShippingAmount(): shipping costs ex VAT ex any discount.
            // - getBaseShippingInclTax(): shipping costs inc VAT ex any discount.
            // - getBaseShippingTaxAmount(): VAT on shipping costs inc discount.
            // - getBaseShippingDiscountAmount(): discount on shipping inc VAT.
            if (!Number::isZero($magentoSource->getBaseShippingAmount())) {
                // We have 2 ways of calculating the vat rate: first one is
                // based on tax amount and normal shipping costs corrected with
                // any discount (as the tax amount is including any discount):
                // $vatRate1 = $magentoSource->getBaseShippingTaxAmount() / ($magentoSource->getBaseShippingInclTax()
                //   - $magentoSource->getBaseShippingDiscountAmount() - $magentoSource->getBaseShippingTaxAmount());
                // However, we will use the 2nd way as that seems to be more
                // precise and thus generally leads to a smaller range:
                // Get range based on normal shipping costs inc and ex VAT.
                $sign = $this->getSign();
                $shippingInc = $sign * $magentoSource->getBaseShippingInclTax();
                $shippingEx = $sign * $magentoSource->getBaseShippingAmount();
                $shippingVat = $shippingInc - $shippingEx;
                $result += array(
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::RecalculateUnitPrice => $this->shippingPricesIncludeTax(),
                    ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,$this->shippingPricesIncludeTax() ? 0.02 : 0.01);
                $result[Meta::FieldsCalculated][] = Meta::VatAmount;

                // getBaseShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getBaseShippingDiscountAmount())) {
                    $result[Meta::LineDiscountAmountInc] = -$sign * $magentoSource->getBaseShippingDiscountAmount();
                } elseif ($this->invoiceSource->getType() === Source::CreditNote
                    && !Number::floatsAreEqual($shippingVat, $magentoSource->getBaseShippingTaxAmount(), 0.02)) {
                    // On credit notes, the shipping discount amount is not
                    // stored but can be deduced via the shipping discount tax
                    // amount and the shipping vat rate. To get a more precise
                    // Meta::LineDiscountAmountInc, we compute that in the
                    // completor when we have corrected the vatrate.
                    $result[Meta::LineDiscountVatAmount] = $sign * ($shippingVat - $sign * $magentoSource->getBaseShippingTaxAmount());
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
        /** @var \Magento\Tax\Model\Config $taxConfig */
        $taxConfig = Registry::getInstance()->create('Magento\Tax\Model\Config');
        return $taxConfig->priceIncludesTax();
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   true if shipping prices include tax, false otherwise.
     */
    protected function shippingPricesIncludeTax()
    {
        /** @var \Magento\Tax\Model\Config $taxConfig */
        $taxConfig = Registry::getInstance()->create('Magento\Tax\Model\Config');
        return $taxConfig->shippingPriceIncludesTax();
    }
}
