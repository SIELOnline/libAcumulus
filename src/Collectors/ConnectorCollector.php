<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

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
 * @method \Siel\Acumulus\Data\Connector collect(PropertySources $propertySources, ?\ArrayObject $fieldSpecifications = null)
 */
class ConnectorCollector extends Collector
{
}
