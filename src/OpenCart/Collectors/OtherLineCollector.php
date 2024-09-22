<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;

/**
 * OtherLineCollector contains OpenCart specific {@see LineType::Other} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class OtherLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An "other" line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectOtherLine($acumulusObject);
    }

    /**
     * Collects a discount line for 1 order total line of type 'handling' or 'low_order_fee'.
     *
     * @param Line $line
     *   An "other" line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function collectOtherLine(Line $line): void
    {
        /**
         * @var array $totalLine
         *   A record from the order_total table + an ex/inc vat indication.
         */
        $totalLine = $this->getPropertySource('otherLineInfo');
        $this->collectTotalLine($line, $totalLine, $totalLine['vat']);
    }
}
