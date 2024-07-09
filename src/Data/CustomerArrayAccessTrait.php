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
     * !!! Erroneous comment !!!
     * The old array based creation process does not use this code at all, so if execution
     * arrives here, we are in the new object based collection and SHOULD use the fiscal
     * address.
     * !!! Erroneous comment !!!
     * The address to map to should be the {@see Customer::getMainAddressType()} (which
     * dictates the {@see Customer::getFiscalAddress()}). However, as the array access is
     * used for backwards compatibility, we choose to map to the invoice address, which
     * was the only address used and sent in the old array based creation process.
     * !!! End of Erroneous comment !!!
     */
    protected function getOffsetMappings(): array
    {
        $result = parent::getOffsetMappings();
        $address = $this->getFiscalAddress();
        if (isset($address)) {
            $addressPropertyDefinitions = $address->getPropertyDefinitions();
            foreach ($addressPropertyDefinitions as $addressPropertyDefinition) {
                $result[strtolower($addressPropertyDefinition['name'])] = [$address, $addressPropertyDefinition['name']];
            }
        }
        return $result;
    }
}
