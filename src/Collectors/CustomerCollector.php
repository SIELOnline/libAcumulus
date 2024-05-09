<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Meta;

/**
 * Collects customer data from the shop.
 *
 * Properties that are mapped:
 * - string $contactId
 * - string $contactYourId
 * - string $salutation
 * - string $website
 * - string $vatNumber
 * - string $telephone
 * - string $telephone2
 * - string $fax
 * - string $email
 * - string $bankAccountNumber
 * - string $mark
 *
 * Properties that are computed using logic:
 * - none
 *
 * Properties that are based on configuration and thus are not set here:
 * - int $type
 * - int $vatTypeId
 * - int $contactStatus
 * - int $overwriteIfExists
 * - int $disableDuplicates
 *
 * Properties that are not set:
 * - none
 *
 * Note that all address data, shipping and invoice address, are placed in
 * separate {@see \Siel\Acumulus\Data\Address} objects.
 */
class CustomerCollector extends Collector
{
    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $taxBasedOn = $this->getVatBasedOn();
        $acumulusObject->metadataSet(Meta::ShopVatBasedOn, $taxBasedOn);
        $taxBasedOnMapping = $this->getVatBasedOnMapping();
        $acumulusObject->setMainAddressType($taxBasedOnMapping[$taxBasedOn] ?? null);
    }

    /**
     * Returns the value of the setting indicating which address is used for tax
     * calculations.
     *
     * The base implementations returns the default setting, {@see AddressType::Invoice},
     * and is to be overridden by shops that do have a setting that specifies the address
     * to use or that always use the shipping address.
     *
     * @return string
     *   Either the (shop specific) value from the corresponding setting in the shop's
     *   config, or one of the constants {@see \Siel\Acumulus\Data\AddressType::Invoice}
     *   or {@see \Siel\Acumulus\Data\AddressType::Shipping}.
     */
    protected function getVatBasedOn(): string
    {
        return AddressType::Invoice;
    }

    /**
     * Returns a mapping for the possible values returned by {@see getVatBasedOn} to an
     * {@see AddressType}.
     *
     * @return string[]
     *   An array with mappings for all values as may be returned by {@see getVatBasedOn}
     *   to one of the constants {@see \Siel\Acumulus\Data\AddressType::Invoice}
     *   or {@see \Siel\Acumulus\Data\AddressType::Shipping}.
     */
    protected function getVatBasedOnMapping(): array
    {
        return [
            AddressType::Shipping => AddressType::Shipping,
            AddressType::Invoice => AddressType::Invoice,
        ];
    }
}
