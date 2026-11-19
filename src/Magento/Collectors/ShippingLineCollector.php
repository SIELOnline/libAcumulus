<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * ShippingLineCollector contains Magento specific {@see LineType::Shipping} collecting
 * logic.
 *
 * @noinspection PhpUnused Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getShippingLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the shipping line for an invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A shipping line with the mapped fields filled in.
     */
    protected function getShippingLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        $source = $propertySources['source'];
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $shopObject */
        $sign = $source->getSign();

        if ($this->shippingPriceIncludeTax()) {
            // May prevent rounding differences between the shop and Acumulus.
            $line->metadataSet(Meta::RecalculatePrice, Fld::UnitPrice);
        }

        // Add vat class meta data.
        $this->addVatClassMetaData($line, $this->getShippingTaxClassId());

        // What do the following methods return?
        // - getBaseShippingAmount(): shipping costs ex VAT ex any discount.
        // - getBaseShippingInclTax(): shipping costs inc VAT ex any discount.
        // - getBaseShippingTaxAmount(): VAT on shipping costs inc discount.
        // - getBaseShippingDiscountAmount(): discount on shipping. However: only exists
        //   on Orders and whether this is inc or ex vat depends on the 'tax =>
        //   calculation => discount_tax' setting.
        $shippingDiscountAmount = $sign * $source->getOrder()->getShopObject()->getBaseShippingDiscountAmount();

        if (!Number::isZero($shippingDiscountAmount)) {
            if ($source->getType() === Source::Order) {
                // Set discount metadata and adjust prices as were set by mapping.
                if ($this->discountIncludesTax()) {
                    $line->metadataSet(Meta::LineDiscountAmountInc, $shippingDiscountAmount);
                    // Adjust unit price inc, remove unit price.
                    $line->metadataSet(Meta::UnitPriceInc, $line->metadataGet(Meta::UnitPriceInc) + $shippingDiscountAmount);
                    $line->metadataSet(Meta::PrecisionUnitPriceInc, $line->metadataGet(Meta::PrecisionUnitPriceInc) + 0.01);
                    unset($line->unitPrice);
                    $line->metadataRemove(Meta::PrecisionUnitPrice);
                } else {
                    $line->metadataSet(Meta::LineDiscountAmount, $shippingDiscountAmount);
                    // Adjust unit price, remove unit price inc.
                    $line->unitPrice += $shippingDiscountAmount;
                    $line->metadataSet(Meta::PrecisionUnitPrice, $line->metadataGet(Meta::PrecisionUnitPrice) + 0.01);
                    $line->metadataRemove(Meta::UnitPriceInc);
                    $line->metadataRemove(Meta::PrecisionUnitPriceInc);
                }
            } else { // Source::Creditmemo
                // On credit notes, the shipping discount amount is not stored but can be
                // deduced via the shipping discount tax amount and the shipping vat rate.
                // To get a more precise Meta::LineDiscountAmountInc, we compute that in
                // the completor when we have corrected the vat rate.
                // These amounts have the expected sign (- on order, + on refund).
                $nonDiscountedShippingVat = $line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice;
                $discountedShippingVat = $line->metadataGet(Meta::VatAmount);
                $shippingVatDiscount = $nonDiscountedShippingVat - $discountedShippingVat;
                $line->metadataSet(Meta::LineDiscountVatAmount, $shippingVatDiscount);
            }
        }
    }
}
