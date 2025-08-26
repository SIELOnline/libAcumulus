<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\Connector;

/**
 * Collects {@see \Siel\Acumulus\Data\Connector} data from the shop.
 *
 * properties that are mapped:
 * - string $application
 * - string $webKoppel
 * - string $development
 * - string $remark
 * - string $sourceUri
 *
 * @method Connector collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null)
 */
class ConnectorCollector extends Collector
{
}
