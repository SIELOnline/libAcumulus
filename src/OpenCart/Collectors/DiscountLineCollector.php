<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;

/**
 * DiscountLineCollector contains OpenCart specific {@see LineType::Discount} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class DiscountLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectDiscountLine($acumulusObject);
    }

    /**
     * Collects a discount line for 1 order total line of type 'discount'.
     *
     * @param Line $line
     *   A discount line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function collectDiscountLine(Line $line): void
    {
        /**
         * @var array $totalLine
         *   A record from the order_total table + an ex/inc vat indication.
         */
        $totalLine = $this->getPropertySource('discountLineInfo');
        $this->collectTotalLine($line, $totalLine, $totalLine['vat']);
    }
}