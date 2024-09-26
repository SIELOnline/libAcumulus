<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;

/**
 * ShippingLineCollector contains OpenCart specific {@see LineType::Shipping} collecting
 * logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectShippingLine($acumulusObject, $propertySources);
    }

    /**
     * Collects a shipping line for 1 order total line of type 'shipping'.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param Line $line
     *   An item line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function collectShippingLine(Line $line, PropertySources $propertySources): void
    {
        /** @var array $totalLine a record from the order_total table + a vat indication */
        $totalLine = $propertySources->get('shippingLineInfo');
        $this->collectTotalLine($line, $totalLine, $totalLine['vat']);
    }
}
