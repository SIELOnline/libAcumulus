<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\Collectors;

use Siel\Acumulus\Collectors\Collector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Tests\TestWebShop\Data\SimpleTestObject;

/**
 * Collets values for a
 * {@See \Siel\Acumulus\Tests\TestWebShop\Data\SimpleTestObject}.
 */
class SimpleTestCollector extends Collector
{
    /**
     * {@inheritDoc}
     */
    protected function getAcumulusObjectType(): string
    {
        return 'SimpleTestObject';
    }

    /**
     * @param SimpleTestObject $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): self
    {
        $reduction = $this->propertySources['customer']['reduction'] ?? 0.0;
        if (isset($acumulusObject->unitPrice)) {
            $acumulusObject->unitPrice = (1 - $reduction) * $acumulusObject->unitPrice;
        }
        return $this;
    }
}
