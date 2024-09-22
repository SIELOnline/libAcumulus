<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;

/**
 * VoucherLineCollector contains OpenCart specific {@see LineType::Voucher} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class VoucherLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A voucher line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectOtherLine($acumulusObject);
    }

    /**
     * Collects a voucher line for 1 order total line of type 'voucher'.
     *
     * @param Line $line
     *   A voucher line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function collectOtherLine(Line $line): void
    {
        /**
         * @var array $totalLine
         *   A record from the order_total table + an ex/inc vat indication.
         */
        $totalLine = $this->getPropertySource('voucherLineInfo');
        $this->collectTotalLine($line, $totalLine, $totalLine['vat']);
    }
}
