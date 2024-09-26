<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Data\Line;

/**
 * LineCollector contains common HikaShop specific {@see Line} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Precision of amounts stored in HS. In HS you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 5
     * digits precision. So 0.0001 is on the pessimistic side.
     */
    protected float $precision = 0.0002;
}
