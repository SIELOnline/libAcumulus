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
    /**
     * Adds address fields to the offset mappings, so they can be accessed via array
     * access as well.
     *
     * The address to map to should be the {@see Customer::getMainAddress()} (which
     * dictates the {@see Customer::getFiscalAddress()}). However, as the array access is
     * used for backwards compatibility, we choose to map to the invoice address, which
     * was the only address used and sent in the old array based creation process.
     */
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
