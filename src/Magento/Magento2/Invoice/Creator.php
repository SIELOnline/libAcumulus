<?php
namespace Siel\Acumulus\Magento\Magento2\Invoice;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
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

    /** @noinspection PhpUnused
     *
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

        $this->addProductInfo($result);

        // For higher precision of the unit price, we will recalculate the price
        // ex vat later on, if product prices are entered inc vat by the admin.
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


        // Get vat and discount information
        // - Tax percent = VAT % as specified in product settings, for the
        //   parent of bundled products this may be 0 and incorrect.
        $vatRate = (float) $item->getTaxPercent();
        // - (Base) tax amount = VAT over discounted item line =
        //   ((product price - discount) * qty) * vat rate.
        // But as discounts get their own lines, this order item line should
        // show the vat amount over the normal, not discounted, price. To get
        // that, we can use the:
        // - (Base) discount tax compensation amount = VAT over line discount.
        // However, it turned out ([SIEL #127821]) that if discounts are applied
        // before tax, this value is 0, so in those cases we can't use that.
        $lineVat = (float) $item->getBaseTaxAmount();
        if (!Number::isZero($item->getBaseDiscountAmount())) {
            // Store discount on this item to be able to get correct discount
            // lines later on in the completion phase.
            $tag = $this->discountIncludesTax() ? Meta::LineDiscountAmountInc : Meta::LineDiscountAmount;
            $result[$tag] = -$item->getBaseDiscountAmount();
            if (!Number::isZero($item->getBaseDiscountTaxCompensationAmount())) {
                $lineVat += (float) $item->getBaseDiscountTaxCompensationAmount();
            } else {
                // We cannot trust lineVat, so do not add it but as we normally
                // have an exact vat rate, this is surplus data anyway.
                $lineVat = null;
            }
        }
        if (isset($lineVat)) {
            $result[Meta::LineVatAmount] = $lineVat;
        }

        // Add VAT related info.
        $childrenItems = $item->getChildrenItems();
        if (Number::isZero($vatRate) && !empty($childrenItems)) {
            // 0 VAT rate on parent: this is probably not correct. The completor
            // will fetch the vat rate from the children.
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

        // Add vat meta data.
        $product = $item->getProduct();
        if ($product) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getVatClassMetaData((int) $product->getTaxClassId());
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

        $this->addProductInfo($result);

        $productPriceEx = -((float) $item->getBasePrice());
        $productPriceInc = -((float) $item->getBasePriceInclTax());

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
            );
        }
        $result[Tag::Quantity] = $item->getQty();

        // Get vat and discount information (also see above getItemLineOrder()):
        $orderItemId = $item->getOrderItemId();
        $vat_rate = null;
        if (!empty($orderItemId)) {
            $orderItem = $item->getOrderItem();
            $vat_rate = $orderItem->getTaxPercent();
        }
        $lineVat = -(float) $item->getBaseTaxAmount();
        if (!Number::isZero($item->getBaseDiscountAmount())) {
            // Store discount on this item to be able to get correct discount
            // lines later on in the completion phase.
            $tag = $this->discountIncludesTax() ? Meta::LineDiscountAmountInc : Meta::LineDiscountAmount;
            $result[$tag] = $item->getBaseDiscountAmount();
            if (!Number::isZero($item->getBaseDiscountTaxCompensationAmount())) {
                $lineVat -= (float) $item->getBaseDiscountTaxCompensationAmount();
            } else {
                // We cannot trust lineVat, so do not add it but as we normally
                // have an exact vat rate, this is surplus data anyway.
                $lineVat = null;
            }
        }
        if (isset($lineVat)) {
            $result[Meta::LineVatAmount] = $lineVat;
        }

        // And the VAT related info.
        if (isset($vat_rate)) {
            $result += array(
                Tag::VatRate => $vat_rate,
                Meta::VatRateSource => static::VatRateSource_Exact,
            );
        } elseif (isset($lineVat)) {
            $result += $this->getVatRangeTags($lineVat / $result[Tag::Quantity], $productPriceEx, 0.02 / min($result[Tag::Quantity], 2), 0.01);
        } else {
            // No exact vat rate and no line vat: just use price inc - price ex.
            $result += $this->getVatRangeTags($productPriceInc - $productPriceEx, $productPriceEx, 0.02, 0.01);
            $result[Meta::FieldsCalculated][] = Meta::VatAmount;
        }

        // Add vat meta data.
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $product = ObjectManager::getInstance()->create(\Magento\Catalog\Model\Product::class);
        $product->getResource()->load($product, $item->getProductId());
        if ($product->getId()) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getVatClassMetaData((int) $product->getTaxClassId());
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
                $sign = $this->invoiceSource->getSign();
                $shippingInc = $sign * $magentoSource->getBaseShippingInclTax();
                $shippingEx = $sign * $magentoSource->getBaseShippingAmount();
                $shippingVat = $shippingInc - $shippingEx;
                $result += array(
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::RecalculatePrice => $this->shippingPriceIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
                    ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,$this->shippingPriceIncludeTax() ? 0.02 : 0.01);
                $result[Meta::FieldsCalculated][] = Meta::VatAmount;

                // Add vat class meta data.
                $result += $this->getVatClassMetaData($this->getShippingTaxClassId());

                // getBaseShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getBaseShippingDiscountAmount())) {
                    $tag = $this->discountIncludesTax() ? Meta::LineDiscountAmountInc : Meta::LineDiscountAmount;
                    $result[$tag] = -$sign * $magentoSource->getBaseShippingDiscountAmount();
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
     * Returns meta data regarding the tax class.
     *
     * @param int $taxClassId
     *   The id of the tax class.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatClassName
     */
    protected function getVatClassMetaData($taxClassId)
    {
        $result = array();
        if ($taxClassId) {
            $result[Meta::VatClassId] = $taxClassId;
            /** @var \Magento\Tax\Model\ClassModel $taxClass */
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            $taxClass = ObjectManager::getInstance()->create(\Magento\Tax\Model\ClassModel::class);
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
    protected function shippingPriceIncludeTax()
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
     * Returns whether a discount amount includes tax.
     *
     * @return bool
     *   true if a discount is applied on the price including tax, false if a
     *   discount is applied on the price excluding tax.
     */
    protected function discountIncludesTax()
    {
        return $this->getTaxConfig()->discountTax();
    }

    /**
     * Returns a \Magento\Tax\Model\Config object.
     *
     * @return \Magento\Tax\Model\Config
     */
    protected function getTaxConfig()
    {
        return Registry::getInstance()->create('Magento\Tax\Model\Config');
    }
}
