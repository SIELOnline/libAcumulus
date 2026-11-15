<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use ArrayObject;
use Carrier;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * ShippingLineCollector contains PrestaShop specific {@see LineType::Shipping} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    protected function collectBefore(AcumulusObject $acumulusObject, PropertySources $propertySources, ArrayObject $fieldSpecifications): void
    {
        parent::collectBefore($acumulusObject, $propertySources, $fieldSpecifications);
        /** @var \Siel\Acumulus\PrestaShop\Invoice\Source $source */
        $source = $propertySources['source'];
        $carrier = new Carrier($source->getOrder()->getShopObject()->id_carrier);
        $propertySources['carrier'] = $carrier;
    }

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     *
     * @noinspection PhpMissingParentCallCommonInspection  Empty parent.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        /** @var Carrier $carrier */
        $carrier = $propertySources['carrier'];
        $this->addVatRateLookupMetadata($acumulusObject, $propertySources['invoice'], $carrier->getIdTaxRulesGroup());
    }
}
