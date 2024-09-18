<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

use function count;
use function is_array;
use function is_object;

/**
 * LineCollector contains common HikaShop specific {Line collecting logic.
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
