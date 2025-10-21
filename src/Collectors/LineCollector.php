<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Meta;

/**
 * Collects invoice line data from the shop and the module's settings.
 *
 * Web shops may not store all properties as defined by Acumulus. So we also collect a lot
 * of metadata, think of unit price including VAT or line price instead of unit price; vat
 * amount instead of vat rate. The idea is that enough information is collected to be able
 * to complete each line.
 *
 * Properties that can be mapped:
 * - string $itemNumber
 * - string $product
 * - string $nature *
 * - float $unitPrice
 * - float $vatRate
 * - float $quantity
 * - float $costPrice
 *
 * Metadata that is "often" mapped:
 * - {@see \Siel\Acumulus\Meta::UnitPriceInc}
 * - {@see \Siel\Acumulus\Meta::VatAmount}
 * - {@see \Siel\Acumulus\Meta::LineAmount}
 * - {@see \Siel\Acumulus\Meta::LineAmountInc}
 * - {@see \Siel\Acumulus\Meta::LineVatAmount}
 * - ...
 *
 * To be able to complete a line, lots of other metadata may be collected in the logic
 * phase, think of information like:
 * - {@see \Siel\Acumulus\Meta::VatClassId}
 * - {@see \Siel\Acumulus\Meta::VatClassName}
 * - {@see \Siel\Acumulus\Meta::VatRateLookup}
 * - {@see \Siel\Acumulus\Meta::VatRateLookupLabel}
 * - {@see \Siel\Acumulus\Meta::VatRateLookupSource}
 * - {@see \Siel\Acumulus\Meta::PrecisionUnitPrice}
 * - ... and many others for other amounts.
 *
 * Properties that may be based on configuration, if not mapped:
 * - string $nature, though it will be rare that Nature can be mapped from data stored in
 *   the web shop.
 *
 * Hierarchically structured invoice lines
 * ---------------------------------------
 * If a shop supports:
 * 1. Options or variants, like size, color, etc.
 * 2. Bundles or composed products
 * Then hierarchical lines are created and stored under the {@see Line::$children}
 * property.
 *
 * Ad 1)
 * For each option or variant you add a child line. Set metadata
 * {@see Meta::VatRateSource} to {@see VatRateSource::Parent}. Copy the quantity from the
 * parent to the child. Price info is probably on the parent line only, unless your shop
 * administers additional or reduced costs for a given option on the child lines in which
 * case the difference should be set as {@see Line::$unitPrice}.
 *
 * Ad 2)
 * For each product that is part of the bundle, add a child line. As this may be a
 * bundle/composed product on its own, you may create multiple levels. There is no
 * maximum depth on child lines.
 *
 * Price info may be on the child lines, but may also be on the parent line,
 * especially so if the bundle is cheaper than its separate parts. The child
 * lines may have their own vat rates, so depending on your situation, fetch the
 * vat info from the child line objects itself or copy it from the parent. When
 * left empty, it is copied from the parent in the Completor phase.
 *
 * Hierarchical lines are "flattened" in the Completor phase, see
 * {@see FlattenerInvoiceLines} based on configuration settings.
 *
 * @method Line collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null)
 */
class LineCollector extends SubTypedCollector
{
    /**
     * This override changes the {@see \Siel\Acumulus\Data\AcumulusObject} type as
     * {@see \Siel\Acumulus\Data\Line} has no subclasses (just subtypes) but does have
     * subtype-specific collectors.
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    protected function getAcumulusObjectType(): string
    {
        return DataType::Line;
    }

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *
     * @noinspection PhpMissingParentCallCommonInspection Base method does not what we want.
     */
    protected function collectBefore(
        AcumulusObject $acumulusObject,
        PropertySources $propertySources,
        ArrayObject $fieldSpecifications
    ): void {
        $acumulusObject->setType($this->subType);
        // ItemLineCollectors only: add the {@see Product} as a property source.
        if ($propertySources->get('item') instanceof Item) {
            /** @var Item $item */
            $item = $propertySources->get('item');
            /** @deprecated use item::getProduct()::... */
            $propertySources->add('product', $item->getProduct());
        }
        $this->getContainer()->getEvent()->triggerLineCollectBefore($acumulusObject, $propertySources);
    }

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    protected function collectAfter(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getContainer()->getEvent()->triggerLineCollectAfter($acumulusObject, $propertySources);
        // Actually for ItemLineCollectors only, but remove won't fail, throw, or alert.
        $propertySources->remove('product');
    }

    /**
     * Returns the shipment method name.
     *
     * This base implementation returns the translated "Shipping costs" string.
     *
     * This base method should be overridden by web shops to provide a more detailed
     * name of the shipping method used.
     *
     * @param mixed ...$args
     *  Any arguments that may be needed by an override.
     *
     * @return string
     *   The name of the shipping method used for the current order.
     */
    protected function getShippingMethodName(mixed ...$args): string
    {
        return $this->t('shipping_costs');
    }

    /**
     * Adds information about the range in which the vat rate will lie.
     *
     * If a web shop does not store the vat rates used in the order, we must
     * calculate them using a (product) price and the vat on it. But as web
     * shops often store these numbers rounded to cents, the vat rate
     * calculation becomes imprecise. Therefore, we compute the range in which
     * it will lie and will let the Completor do a comparison with the actual
     * vat rates that an order can have.
     * - If $denominator = 0 (free product), the vat rate will be set to null
     *   and the Completor will try to get this line listed under the correct
     *   vat rate.
     * - If $numerator = 0, the vat rate will be set to 0 and be treated as if it
     *   is an exact vat rate, not a vat range.
     *
     *  The following fields and metadata will be set/added::
     *  - vatRate
     *  - 'vatamount'
     *  - 'meta-vatrate-min'
     *  - 'meta-vatrate-max'
     *  - 'meta-vatamount-precision'
     *  - 'meta-vatrate-source'
     *
     * @param float $numerator
     *   The amount of VAT as received from the web shop.
     * @param float $denominator
     *   The price of a product excluding VAT as received from the web shop.
     * @param float $numeratorPrecision
     *   The precision used when rounding the number. This means that the
     *   original numerator will not differ more than half of this.
     * @param float $denominatorPrecision
     *   The precision used when rounding the number. This means that the
     *   original denominator will not differ more than half of this.
     *
     * @deprecated : Move this from the collector to the completor phase:
     *   DONE left for reference.
     */
    public static function addVatRangeTags(
        Line $line,
        float $numerator,
        float $denominator,
        float $numeratorPrecision = 0.01,
        float $denominatorPrecision = 0.01
    ): void {
        if (Number::isZero($denominator, 0.0001)) {
            // A zero amount (and I hope zero VAT): we cannot determine the range.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatAmount, $numerator);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        } elseif (Number::isZero($numerator, 0.0001)) {
            // zero VAT with non-zero amount: rate = 0
            $line->vatRate = 0;
            $line->metadataSet(Meta::VatAmount, $numerator);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact0);
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $line->vatRate = 100.0 * $range['calculated'];
            $line->metadataSet(Meta::VatRateMin, 100.0 * $range['min']);
            $line->metadataSet(Meta::VatRateMax, 100.0 * $range['max']);
            $line->metadataSet(Meta::VatAmount, $numerator);
            $line->metadataSet(Meta::PrecisionUnitPrice, $denominatorPrecision);
            $line->metadataSet(Meta::PrecisionVatAmount, $numeratorPrecision);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Calculated);
        }
    }
}
