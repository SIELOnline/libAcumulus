<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * Allows access to {@see Customer} with array bracket syntax and Acumulus tags (all lower
 * case).
 *
 * This trait overrides
 * {@see \Siel\Acumulus\Data\AcumulusObjectArrayAccessTrait::getOffsetMappings()}.
 *
 */
trait CustomerArrayAccessTrait
{
    protected function getOffsetMappings(): array
    {
        $result = parent::getOffsetMappings();
        if (isset($this->invoiceAddress)) {
            $addressPropertyDefinitions = $this->invoiceAddress->getPropertyDefinitions();
            foreach ($addressPropertyDefinitions as $addressPropertyDefinition) {
                $result[strtolower($addressPropertyDefinition['name'])] = [$this->invoiceAddress, $addressPropertyDefinition['name']];
            }
        }
        return $result;
    }
}
