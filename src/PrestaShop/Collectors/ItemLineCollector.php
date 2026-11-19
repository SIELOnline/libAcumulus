<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * ItemLineCollector contains PrestaShop specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * This PrestaShop override collects:
     * -
     * - ...
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @noinspection PhpMissingParentCallCommonInspection empty parent.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getItemLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the logic fields for 1 item line, for an order or credit slip.
     *
     * An item will contain information as detailed on the
     * {@see \Siel\Acumulus\PrestaShop\Invoice\Item} class.
     *
     * @param Line $line
     *   An item line with the mapped fields filled in.
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $propertySources['invoice'];
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $propertySources['source'];
        /** @var \Siel\Acumulus\Invoice\Item $item */
        $item = $propertySources['item'];
        /** @var array $shopItem */
        $shopItem = $item->getShopObject();

        if ($source->getType() === Source::CreditNote) {
            // The amounts of the order_detail_tax record may be incorrect if not all
            // articles were returned.
            $line->metadataRemove(Meta::VatAmount);
            $line->metadataRemove(Meta::LineVatAmount);
        } elseif ($this->lineWasDiscounted($line)) {
            $realLineVatAmount = $line->metadataGet(Meta::LineAmountInc) - $line->metadataGet(Meta::LineAmount);
            $line->metadataSet(Meta::LineDiscountVatAmount, $line->metadataGet(Meta::LineVatAmount) - $realLineVatAmount);
            $line->metadataRemove(Meta::VatAmount);
            $line->metadataRemove(Meta::LineVatAmount);
        }


        $taxRulesGroupId = isset($shopItem['id_tax_rules_group']) ? (int) $shopItem['id_tax_rules_group'] : 0;
        $this->addVatRateLookupMetadata($line, $propertySources['invoice'], $taxRulesGroupId);
    }

    /**
     * Returns whether this order line was discounted.
     *
     * Discounts based on coupons, cart discounts in PS, do get their own DiscountLine,
     * or lines when they are split over multiple VAT rates. So an ItemLine should contain
     * amounts related to the price before a coupon discount (though they may include
     * catalogue or volume discounts).
     *
     * Most amounts are correct, but the amounts that come from the order_detail_tax
     * table, 'unit_amount' and 'total_amount', are after discount, so ItemLine properties
     * or metadata based on these, should be corrected or unset. But this information is
     * necessary to split a coupon over various VAT rates that appear in this invoice.
     *
     * NOTE: For a credit note, it is possible that not all items of this line were
     * returned, making 'total_amount' incorrect, and/or that any voucher discount applied
     * is now cancelled fully and thus spread over the returned articles only, making a
     * discounted 'unit_amount' incorrect. So we should not call this for credit notes.
     */
    protected function lineWasDiscounted(Line $line): bool
    {
        return !Number::floatsAreEqual(
            $line->metadataGet(Meta::LineVatAmount) ,
            $line->metadataGet(Meta::LineAmountInc) - $line->metadataGet(Meta::LineAmount)
        );
    }
}
