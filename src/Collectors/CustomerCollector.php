<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
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
     * This override collects the fields of a {@see \Siel\Acumulus\Data\Customer} object,
     * as well as of its 2 {@see \Siel\Acumulus\Data\Address} child properties.
     *
     * @return \Siel\Acumulus\Data\Customer
     */
    public function collect(PropertySources $propertySources, ?array $fieldSpecifications): AcumulusObject
    {
        /** @var Customer $customer */
        $customer = parent::collect($propertySources, $fieldSpecifications);

        $propertySources->add('customer', $customer);
        $customer->setInvoiceAddress($this->collectAddress(AddressType::Invoice, $propertySources));
        $customer->setShippingAddress($this->collectAddress(AddressType::Shipping, $propertySources));

        // @todo: what to do if we have an "empty" address? (see OC examples)
        //   - When to consider an address as being empty?
        //   - Copy all fields or copy only empty fields (the latter seems to contradict
        //     the concept of what an "empty" address constitutes).

        return $customer;
    }

    /**
     * @param string $subType
     *   One of the {@see AddressType} constants Invoice or Shipping.
     */
    public function collectAddress(string $subType, PropertySources $propertySources): Address
    {
        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $this->getContainer()->getCollector(DataType::Address, $subType)->collect($propertySources, null);
        return $address;
    }


    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        // @todo: in fact, this is not the correct place to retrieve and store this. This
        //   should be either "global" metadata (except in OC) or at the invoice level
        //   where it is used for checking valid vat rates.
        $taxBasedOn = $this->getVatBasedOn();
        $acumulusObject->metadataSet(Meta::ShopVatBasedOn, $taxBasedOn);
        $taxBasedOnMapping = $this->getVatBasedOnMapping();
        $acumulusObject->setMainAddressType($taxBasedOnMapping[$taxBasedOn] ?? null);
    }

    /**
     * Returns the value of the setting indicating which address is used for tax
     * calculations.
     *
     * The base implementations returns the default setting ({@see AddressType::Invoice})
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
