<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\Contract;

/**
 * Collects {@see \Siel\Acumulus\Data\Contract} data from the shop.
 *
 * properties that are mapped:
 * - string $contractCode
 * - string $userName
 * - string $password
 * - string $emailOnError
 * - string $emailOnWarning
 *
 * @method Contract collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null)
 */
class ContractCollector extends Collector
{
}
