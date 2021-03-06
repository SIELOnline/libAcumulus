<?php
namespace Siel\Acumulus\Magento\Magento1\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Magento\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento
 * order or credit memo.
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

        /** @var \Mage_Sales_Model_Order|\\Mage_Sales_Model_Order_Creditmemo $source */
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

        $this->propertySources['customer'] = \Mage::getModel('customer/customer')->load($source->getCustomerId());
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
            if (!Number::isZero($item->getBaseRowTotal())) {
                $result[] = $this->getItemLineCreditNote($item);
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param \Mage_Sales_Model_Order_Item $item
     */
    protected function getItemLineOrder($item, $isChild = false)
    {
        $result = array();

        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);

        // For higher precision of the unit price, we will recalculate the price
        // ex vat if product prices are entered inc vat by the admin.
        $productPriceEx = (float) $item->getBasePrice();
        $productPriceInc = (float) $item->getBasePriceInclTax();

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $productPriceInc;
        } else {
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::RecalculatePrice => $this->productPricesIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
            );
        }
        $result[Tag::Quantity] = $item->getQtyOrdered();

        // Tax amount = VAT over discounted product price.
        // Hidden tax amount = VAT over discount.
        // Tax percent = VAT % as specified in product settings, for the parent
        // of bundled products this may be 0 and incorrect.
        // But as discounts get their own lines and the product lines are
        // showing the normal (not discounted) price we add these 2.
        $vatRate = (float) $item->getTaxPercent();
        $lineVat = (float) $item->getBaseTaxAmount() + (float) $item->getBaseHiddenTaxAmount();

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

        // Add vat meta data.
        $product = $item->getProduct();
        if ($product) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getTaxClassMetaData((int) $product->getTaxClassId());
        }

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
     * @param \Mage_Sales_Model_Order_Creditmemo_Item $item
     *
     * @return array
     */
    protected function getItemLineCreditNote(\Mage_Sales_Model_Order_Creditmemo_Item $item)
    {
        $result = array();

        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);

        $productPriceEx = -((float) $item->getBasePrice());
        $productPriceInc = -((float) $item->getBasePriceInclTax());
        $lineVat = -((float) $item->getBaseTaxAmount() + (float) $item->getBaseHiddenTaxAmount());

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $productPriceInc;
        } else {
            // Add price info.
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::RecalculatePrice => $this->productPricesIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
                Meta::LinesVatAmount => $lineVat,
            );
        }
        $result[Tag::Quantity] = $item->getQty();

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

        // Add vat meta data.
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product');
        $product->getResource()->load($product, $item->getProductId());
        if ($product->getId()) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getTaxClassMetaData((int) $product->getTaxClassId());
        }

        // Add discount related info.
        if (!Number::isZero($item->getBaseDiscountAmount())) {
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
        /** @var \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Creditmemo $magentoSource */
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
                $sign = $this->invoiceSource->getSign();
                $shippingInc = $sign * $magentoSource->getBaseShippingInclTax();
                $shippingEx = $sign * $magentoSource->getBaseShippingAmount();
                $shippingVat = $shippingInc - $shippingEx;
                $result += array(
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::RecalculatePrice => $this->shippingPricesIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
                    ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,$this->shippingPricesIncludeTax() ? 0.02 : 0.01);
                $result[Meta::FieldsCalculated][] = Meta::VatAmount;

                // Add vat meta data.
                $result += $this->getTaxClassMetaData($this->getShippingTaxClassId());

                // getShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getBaseShippingDiscountAmount())) {
                    $result[Meta::LineDiscountAmountInc] = -$sign * $magentoSource->getBaseShippingDiscountAmount();
                } elseif ($this->invoiceSource->getType() === Source::CreditNote
                    && !Number::floatsAreEqual($shippingVat, $magentoSource->getBaseShippingTaxAmount(), 0.02)) {
                    // On credit notes, the shipping discount amount is not stored but can
                    // be deduced via the shipping discount tax amount and the shipping vat
                    // rate. To get a more precise Meta::LineDiscountAmountInc, we
                    // compute that in the completor when we have corrected the vatrate.
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
     * Returns meta data regarding the tax class.
     *
     * @param int $taxClassId
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatClassName
     */
    protected function getTaxClassMetaData($taxClassId)
    {
        $result = array();
        if ($taxClassId) {
            $result[Meta::VatClassId] = $taxClassId;
            /** @var \Mage_Tax_Model_Class $taxClass */
            $taxClass = \Mage::getModel('tax/class');
            $taxClass->getResource()->load($taxClass, $taxClassId);
            $result[Meta::VatClassName] = $taxClass->getClassName();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function productPricesIncludeTax()
    {
        return $this->getTaxConfig()->priceIncludesTax();
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   true if shipping prices include tax, false otherwise.
     */
    protected function shippingPricesIncludeTax()
    {
        return $this->getTaxConfig()->shippingPriceIncludesTax();
    }

    /**
     * Returns the shipping tax class id.
     *
     * @return int
     *   The id of the tax class used for shipping.
     */
    protected function getShippingTaxClassId()
    {
        return $this->getTaxConfig()->getShippingTaxClass();
    }

    /**
     * Returns a \Mage_Tax_Model_Config object.
     *
     * @return false|\Mage_Tax_Model_Config
     */
    protected function getTaxConfig()
    {
        return \Mage::getModel('tax/config');
    }

}
