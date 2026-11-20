<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Invoice\Item;
use Siel\Acumulus\Meta;

use function count;

/**
 * ItemLineCollector contains Magento specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getItemLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   An item line with the mapped fields filled in.
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        $source = $propertySources['source'];
        /** @var \Siel\Acumulus\Magento\Invoice\Item $item */
        $item = $propertySources['item'];

        if ($source->getType() === Source::Order) {
            $this->getItemLineOrder($line, $item, $source);
        } else {
            $this->getItemLineCreditNote($line, $item, $source);
        }
    }

    /**
     * Returns an item line for 1 main product line.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function getItemLineOrder(Line $line, Item $item, Source $source): void
    {
        $sign = $source->getSign();
        /** @var OrderItemInterface $shopItem */
        $shopItem = $item->getShopObject();

        // For higher precision of the unit price, we will recalculate the price
        // ex-vat later on if product prices are entered inc vat by the admin.
        if ($this->productPricesIncludeTax()) {
            $line->metadataSet(Meta::RecalculatePrice, Fld::UnitPrice);
        }

        // Get vat and discount information
        // - (Base) tax amount = VAT on discounted item line =
        //   ((product price - discount) * qty) * vat rate.
        // But as discounts get their own lines, this order item line should
        // show the vat amount over the normal, not discounted, price. To get
        // that, we can use the:
        // - [Base]discount tax compensation amount = VAT over line discount.
        // However, it turned out ([SIEL #127821]) that if discounts are applied
        // before tax, this value is 0, so in those cases we can't use that.

        // Store discount on this item to be able to get correct discount lines later on
        // in the completor phase.
        $itemDiscountAmount = -$sign * $shopItem->getBaseDiscountAmount();
        if (!Number::isZero($itemDiscountAmount)) {
            if ($this->discountIncludesTax()) {
                $line->metadataSet(Meta::LineDiscountAmountInc, $itemDiscountAmount);
            } else {
                $line->metadataSet(Meta::LineDiscountAmount, $itemDiscountAmount);
            }
        }

        // Add VAT related info.
        $childItems = $shopItem->getChildrenItems();
        if (Number::isZero($line->vatRate) && !empty($childItems)) {
            // 0 VAT rate on parent: this is probably not correct, but can
            // happen with configurable products. If there's only 1 child, and
            // that child is the same as this parent, vat rate is taken from the
            // child anyway, so the vat (class) info will be copied over from
            // the child further on in this method. If not the completor will
            // have to do something:
            unset($line->vatRate);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        } elseif (Number::isZero($line->vatRate) && Number::isZero($line->unitPrice) && !$line->metadataGet(Meta::IsChild)) {
            // 0 vatrate and a zero price on a main item: when the invoice gets
            // send on order creation, I have seen child lines on their own,
            // i.e. not being attached to their parent, while at the same time
            // the parent did have (a copy of) that child under its
            // child items. We bail out by adding the metadata tag DoNotAdd and returning,
            // this will ensure that the parent line is not added but the child line is.
            $line->metadataSet(Meta::DoNotAdd, true);
            return;
        }

        // Add vat metadata.
        if ($item->getProduct() !== null) {
            $this->addVatClassMetaData($line, $line->metadataGet(Meta::VatClassId));
        }

        // Add composed products or product variant.
        if (!empty($childItems)) {
            foreach ($childItems as $shopChildItem) {
                /** @var Line $childLine */
                $childLine = $this->collectChild(['item' => $this->getContainer()->createItem($shopChildItem, $source)]);
                if (!$childLine->metadataGet(Meta::DoNotAdd)) {
                    $line->addChild($childLine);
                }
            }
            if ($this->isChildSameAsParent($line, $line->getChildren())) {
                $line->metadataSet(Meta::ChildSameAsParent, true);
                // A configurable product having 1 child means the child is the
                // chosen variant: copy the product id and name, and vat info from the
                // child to the parent and remove the child.
                $childLine = $line->getChildren()[0];
                $line->product = $childLine->product;
                $line->metadataSet(Meta::ProductId, $childLine->metadataGet(Meta::ProductId));
                // We may have to copy vat data.
                if (empty($line->vatRate) && !empty($childLine->vatRate)) {
                    $line->vatRate = $childLine->vatRate;
                    $line->metadataSet(Meta::VatRateSource, VatRateSource::Child);
                    if (!empty($childLine->metadataGet(Meta::VatRateLookup))) {
                        $line->metadataSet(Meta::VatRateLookup, $childLine->metadataGet(Meta::VatRateLookup));
                    } else {
                        $line->metadataRemove(Meta::VatRateLookup);
                    }
                    if (!empty($childLine->metadataGet(Meta::VatRateLookupSource))) {
                        $line->metadataSet(Meta::VatRateLookupSource, $childLine->metadataGet(Meta::VatRateLookupSource));
                    } else {
                        $line->metadataRemove(Meta::VatRateLookupSource);
                    }
                    if (!empty($childLine->metadataGet(Meta::VatClassId))) {
                        $line->metadataSet(Meta::VatClassId, $childLine->metadataGet(Meta::VatClassId));
                    } else {
                        $line->metadataRemove(Meta::VatClassId);
                    }
                    if (!empty($childLine->metadataGet(Meta::VatClassName))) {
                        $line->metadataSet(Meta::VatClassName, $childLine->metadataGet(Meta::VatClassName));
                    } else {
                        $line->metadataRemove(Meta::VatClassName);
                    }
                }
                $line->removeChildren();
            }
        }

        // Add customisable options.
        $customizableOptions = $shopItem->getProductOptionByCode('options');
        if (!empty($customizableOptions)) {
            foreach ($customizableOptions as $customizableOption) {
                /** @var Line $child */
                $child = $this->createAcumulusObject();
                $child->metadataSet(Meta::IsChild, true);
                $child->metadataSet(Meta::ProductType, 'option');
                $child->metadataSet(Meta::ProductId, $customizableOption['option_id'] . ': ' . $customizableOption['option_value']);
                $child->product = $customizableOption['label'] . ': ' . $customizableOption['print_value'];
                $child->quantity = $line->quantity;
                $child->unitPrice = 0;
                $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                $line->addChild($child);
            }
        }
    }

    /**
     * Returns an item line for 1 main product line.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function getItemLineCreditNote(Line $line, Item $item, Source $source): void
    {
        $sign = $source->getSign();
        /** @var CreditmemoItemInterface $shopItem */
        $shopItem = $item->getShopObject();

        if ($this->productPricesIncludeTax()) {
            $line->metadataSet(Meta::RecalculatePrice, Fld::UnitPrice);
        }

        // Store discount on this item to be able to get correct discount lines later on
        // in the completor phase.
        $itemDiscountAmount = -$sign * $shopItem->getBaseDiscountAmount();
        if (!Number::isZero($itemDiscountAmount)) {
            if ($this->discountIncludesTax()) {
                $line->metadataSet(Meta::LineDiscountAmountInc, $itemDiscountAmount);
            } else {
                $line->metadataSet(Meta::LineDiscountAmount, $itemDiscountAmount);
            }
        }

        // Add vat metadata.
        if ($item->getProduct() !== null) {
            $this->addVatClassMetaData($line, $line->metadataGet(Meta::VatClassId));
        }

        // On a credit note we only have single lines, no compound lines, thus
        // no children that might have to be added.
        // @todo: but do we have options and variants?
    }

    /**
     * Returns whether a single child line is actually the "same" as its parent.
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
     * @param Line $line
     * @param Line[] $children
     *
     * @return bool
     *   True if the single child line is actually the "same" as its parent.
     */
    protected
    function isChildSameAsParent(
        Line $line,
        array $children
    ): bool {
        if ($line->metadataGet(Meta::ProductType) === 'configurable' && count($children) === 1) {
            $child = reset($children);
            if ($line->itemNumber === $child->itemNumber && $line->quantity === $child->quantity && Number::isZero($child->unitPrice)) {
                return true;
            }
        }
        return false;
    }
}
