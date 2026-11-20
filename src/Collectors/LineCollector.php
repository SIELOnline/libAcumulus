<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
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
    private PropertySources $propertySources;
    private ?ArrayObject $fieldSpecifications;

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
    protected function collectBefore(AcumulusObject $acumulusObject, PropertySources $propertySources, ArrayObject $fieldSpecifications): void
    {
        // Create a reference to these 2 values to be able to use them when collecting a
        // child.
        $this->propertySources = $propertySources;
        $this->fieldSpecifications = $fieldSpecifications;

        $acumulusObject->setType($this->subType);
        // ItemLineCollectors only: add the {@see Product} as a property source.
        $item = $propertySources['item'] ?? null;
        if ($item instanceof Item) {
            /** @deprecated use item::getProduct()::... */
            $propertySources['product'] = $item->getProduct();
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
     * Collects a child Line.
     *
     * Collecting a child line differs from collecting a line in that:
     * - The {@see Collector::collectBefore()} and {@see Collector::collectAfter()} are
     *   not called.
     * - The collectLineBefore and collectLineAfter events are not triggered.
     * - The Line gets a Meta::IsChild = true metadata value.
     * - PropertySources passed are additional and will temporarily be added during
     *   collecting this child line.
     *
     * @param array $additionalPropertySources
     *   Additional property sources to be added to the originally set. If a property
     *   source with the same name already exists, it will (temporartily) be overwritten.
     *
     * @return \Siel\Acumulus\Data\AcumulusObject
     *   The collected child line.
     */
    protected function collectChild(array $additionalPropertySources): AcumulusObject
    {
        $oldPropertySources = $this->propertySources->add($additionalPropertySources);
        $acumulusObject = $this->createAcumulusObject();
        $acumulusObject->metadataSet(Meta::IsChild, true);
        $this->collectFields($acumulusObject, $this->propertySources, $this->fieldSpecifications);
        $this->propertySources->exchangeArray($oldPropertySources);
        return $acumulusObject;
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
}
