<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Collectors;

use Siel\Acumulus\Collectors\Collector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Collets values for a
 * {@See \Siel\Acumulus\TestWebShop\Data\SimpleTestObject}.
 */
class SimpleTestObjectCollector extends Collector
{
    /**
     * @param SimpleTestObject $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $reduction = $this->getPropertySource('customer')['reduction'] ?? 0.0;
        if (isset($acumulusObject->unitPrice)) {
            $acumulusObject->unitPrice = (1 - $reduction) * $acumulusObject->unitPrice;
        }
    }
}
