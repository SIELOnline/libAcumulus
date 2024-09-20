<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

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
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectShippingLine($acumulusObject);
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
    protected function collectShippingLine(Line $line): void
    {
        /** @var array $totalLine a record from the order_total table + a vat indication */
        $totalLine = $this->getPropertySource('shippingLineInfo');
        $this->collectTotalLine($line, $totalLine, $totalLine['vat']);
    }
}
