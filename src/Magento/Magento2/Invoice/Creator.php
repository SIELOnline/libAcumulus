<?php
namespace Siel\Acumulus\Magento\Magento2\Invoice;

use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Magento\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
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
     * Returns the item lines for a credit mote.
     *
     * @noinspection PhpUnused Called via Creator::callSourceTypeSpecificMethod().
     */
    protected function getItemLinesCreditNote()
    {
        $result = [];
        // Items may be composed, so start with all "visible" items.
        foreach ($this->creditNote->getAllItems() as $item) {
            // Only items for which row total is set, are refunded
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
     */
    protected function getItemLineOrder($item, $isChild = false)
    {
        $result = [];

        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);
        $result[Meta::Id] = $item->getId();
        $result[Meta::ProductType] = $item->getProductType();
        $result[Meta::ProductId] = $item->getProductId();

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
            $result += [
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::RecalculatePrice => $this->productPricesIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
            ];
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
            // 0 VAT rate on parent: this is probably not correct, but can
            // happen with configurable products. If there's only 1 child, and
            // that child is the same as this parent, vat rate is taken form the
            // child anyway, so the vat (class) info will be copied over from
            // the child further on in this method. If not the completor will
            // have to do something:
            // @todo: should we do this here or in the completor?
            $result += [
                Tag::VatRate => null,
                Meta::VatRateSource => Creator::VatRateSource_Completor,
                Meta::VatRateLookup => $vatRate,
                Meta::VatRateLookupSource => '$item->getTaxPercent()',
            ];
        } elseif (Number::isZero($vatRate) && Number::isZero($productPriceEx) && !$isChild) {
            // 0 vat rate and zero price on a main item: when the invoice gets
            // send on order creation, I have seen child lines on their own,
            // i.e. not being attached to their parent, while at the same time
            // the parent did have (a copy of) that child under its
            // childrenItems. We bail out by returning null.
            return null;
        } else {
            // No 0 VAT, or 0 vat and not a parent product and not a zero price:
            // the vat rate is real.
            $result += [
                Tag::VatRate => $vatRate,
                Meta::VatRateSource => Number::isZero($vatRate) ? Creator::VatRateSource_Exact0 : Creator::VatRateSource_Exact,
            ];
        }

        // Add vat meta data.
        $product = $item->getProduct();
        if ($product) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getVatClassMetaData($product->getTaxClassId());
        }

        // Add children lines for customisable options and composed products.
        // For a configurable product, some info of the chosen variant will be
        // merged directly into the parent.
        $result[Meta::ChildrenLines] = [];

        // Add composed products or product variant.
        if (!empty($childrenItems)) {
            $childrenLines = [];
            foreach ($childrenItems as $child) {
                $childLine = $this->getItemLineOrder($child, true);
                if ($childLine !== null) {
                    $childrenLines[] = $childLine;
                }
            }
            if ($this->isChildSameAsParent($result, $childrenLines)) {
                // A configurable product having 1 child means the child is the
                // chosen variant: use the product id and name of the child.
                // @todo: should we do this here or in the completor?
                $childLine = reset($childrenLines);
                $result[Tag::Product] = $childLine[Tag::Product];
                $result[Meta::ProductId] = $childLine[Meta::ProductId];
                // We may have to copy vat data.
                if (empty($result[Tag::VatRate]) && $childLine[Tag::VatRate] !== $result[Tag::VatRate]) {
                    $result[Tag::VatRate] = $childLine[Tag::VatRate];
                    $result[Meta::VatRateSource] = Creator::VatRateSource_Child;
                    if (!empty($childLine[Meta::VatRateLookup])) {
                        $result[Meta::VatRateLookup] = $childLine[Meta::VatRateLookup];
                    } else {
                        unset($result[Meta::VatRateLookup]);
                    }
                    if (!empty($childLine[Meta::VatRateLookupSource])) {
                        $result[Meta::VatRateLookupSource] = $childLine[Meta::VatRateLookupSource];
                    } else {
                        unset($result[Meta::VatRateLookupSource]);
                    }
                    if (!empty($childLine[Meta::VatClassId])) {
                        $result[Meta::VatClassId] = $childLine[Meta::VatClassId];
                    } else {
                        unset($result[Meta::VatClassId]);
                    }
                    if (!empty($childLine[Meta::VatClassName])) {
                        $result[Meta::VatClassName] = $childLine[Meta::VatClassName];
                    } else {
                        unset($result[Meta::VatClassName]);
                    }
                }
            } else {
                $result[Meta::ChildrenLines] = array_merge($result[Meta::ChildrenLines], $childrenLines);
            }
        }

        // Add customizable options.
        $customizableOptions = $item->getProductOptionByCode('options');
        if (!empty($customizableOptions)) {
            foreach ($customizableOptions as $customizableOption) {
                $child = [];
                $child[Meta::ProductType] = 'option';
                $child[Meta::ProductId] = $customizableOption['option_id'] . ': ' . $customizableOption['option_value'];
                $child[Tag::Product] = $customizableOption['label'] . ': ' . $customizableOption['print_value'];
                $child[Tag::Quantity] = $result[Tag::Quantity];
                $child[Tag::UnitPrice] = 0;
                $child[Meta::VatRateSource] = static::VatRateSource_Parent;
                $result[Meta::ChildrenLines][] = $child;
            }
        }

        // Unset children lines if no children were added.
        if (empty($result[Meta::ChildrenLines])) {
            unset($result[Meta::ChildrenLines]);
        }

        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Returns whether a single child line is actually the same as its parent.
     *
     * If:
     * - the parent is a configurable product
     * - there is exactly 1 child line
     * - for the same item number and quantity
     * - with no price info on the child
     * We are processing a configurable product that contains the chosen variant
     * as single child: do not add the child, but copy the product description
     * to the result as it contains more option descriptions.
     *
     * @param array $parent
     * @param array[] $children
     *
     * @return bool
     *   True if the single child line is actually the same as its parent.
     */
    protected function isChildSameAsParent(array $parent, array $children)
    {
        if ($parent[Meta::ProductType] === 'configurable' && count($children) === 1) {
            $child = reset($children);
            if ($parent[Tag::ItemNumber] === $child[Tag::ItemNumber]
                && $parent[Tag::Quantity] === $child[Tag::Quantity]
                && Number::isZero($child[Tag::UnitPrice])
            ) {
                return true;
            }
        }
        return false;
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
        $result = [];

        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);

        /** @noinspection PhpCastIsUnnecessaryInspection */
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
            $result += [
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::RecalculatePrice => $this->productPricesIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
            ];
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
            $result += [
                Tag::VatRate => $vat_rate,
                Meta::VatRateSource => static::VatRateSource_Exact,
            ];
        } elseif (isset($lineVat)) {
            $result += $this->getVatRangeTags($lineVat / $result[Tag::Quantity], $productPriceEx, 0.02 / min($result[Tag::Quantity], 2), 0.01);
        } else {
            // No exact vat rate and no line vat: just use price inc - price ex.
            $result += $this->getVatRangeTags($productPriceInc - $productPriceEx, $productPriceEx, 0.02, 0.01);
            $result[Meta::FieldsCalculated][] = Meta::VatAmount;
        }

        // Add vat meta data.
        /** @var \Magento\Catalog\Model\Product $product */
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $product = Registry::getInstance()->create(\Magento\Catalog\Model\Product::class);
        Registry::getInstance()->get($product->getResourceName())->load($product, $item->getProductId());
        if ($product->getId()) {
            /** @noinspection PhpUndefinedMethodInspection */
            $result += $this->getVatClassMetaData($product->getTaxClassId());
        }

        // On a credit note we only have single lines, no compound lines, thus
        // no children that might have to be added.
        // @todo: but do we have options and variants?

        $this->removePropertySource('item');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = [];
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $magentoSource */
        $magentoSource = $this->invoiceSource->getSource();
        // Only add a free shipping line on an order, not on a credit note:
        // free shipping is never refunded...
        if ($this->invoiceSource->getType() === Source::Order || !Number::isZero($magentoSource->getBaseShippingAmount())) {
            $result += [
                Tag::Product => $this->getShippingMethodName(),
                Tag::Quantity => 1,
            ];

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
                $result += [
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::RecalculatePrice => $this->shippingPriceIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
                           ] + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02,$this->shippingPriceIncludeTax() ? 0.02 : 0.01);
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
                $result += [
                    Tag::UnitPrice => 0,
                    Tag::VatRate => null,
                    Meta::VatRateSource => static::VatRateSource_Completor,
                ];
            }
        }
        return $result;
    }

    /**
     * Returns meta data regarding the tax class.
     *
     * @param int|null $taxClassId
     *   The id of the tax class.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatClassName
     */
    protected function getVatClassMetaData($taxClassId)
    {
        $result = [];
        if ($taxClassId) {
            $taxClassId = (int) $taxClassId;
            $result[Meta::VatClassId] = $taxClassId;
            /** @var \Magento\Tax\Model\ClassModel $taxClass */
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            $taxClass = Registry::getInstance()->create(\Magento\Tax\Model\ClassModel::class);
            Registry::getInstance()->get($taxClass->getResourceName())->load($taxClass, $taxClassId);
            $result[Meta::VatClassName] = $taxClass->getClassName();
        } else {
            $result[Meta::VatClassId] = Config::VatClass_Null;
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
