<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;

class CollectCustomer extends Collect
{
    public function collect(array $propertySources): Customer
    {
        $this->propertySources = $propertySources;
        /** @var Source $invoiceSource */
        $invoiceSource = $this->propertySources['invoiceSource'];
        $customer = new Customer();

        $customerSettings = $this->config->getCustomerSettings();

        // Identifying and status fields.
        // @todo: contactId?
        // @todo: type: this is not collecting but completing.
        $customer->setType($customerSettings['defaultCustomerType']);
        $customer->setContactYourId($this->expand($customerSettings['contactYourId']));
        $customer->setContactStatus($this->expand($customerSettings['contactStatus']));

        // Address 1.  @todo: separate address into own object, always collect both and decide if and how to use them in the completor
        $customer->setCompanyName1($this->expand($customerSettings['companyName1']));
        $customer->setCompanyName2($this->expand($customerSettings['companyName2']));
        $customer->setFullName($this->expand($customerSettings['fullName']));
        $customer->setSalutation($this->expand($customerSettings['salutation']));
        $customer->setAddress1($this->expand($customerSettings['address1']));
        $customer->setAddress2($this->expand($customerSettings['address2']));
        $customer->setPostalCode($this->expand($customerSettings['postalCode']));
        $customer->setCity($this->expand($customerSettings['city']));
        $customer->setCountryCode($invoiceSource->getCountryCode());
        // Add 'nl' as default country code.
        $customer->setCountryCode('nl', AcumulusProperty::Set_NotOverwrite);
        // @todo: how to handle country name? : collect and let completer decide whether to use it or not.
        //$customer->setCountry($this->countries->getCountryName($customer[Fld::CountryCode]), AcumulusProperty::Set_NotEmpty);
        // @todo: countryAutoName? (this is not collecting but completing)
        // @todo: countryAutoNameLang? (this is not collecting but completing)

        // Address 2.
        // @todo: all fields of 2nd address

        // Other fields.
        // @todo: website?
        $customer->setVatNumber($this->expand($customerSettings['vatNumber']));
        $customer->setTelephone($this->expand($customerSettings['telephone']));
        // @todo: telephone2? (use fax?)
        $customer->setFax($this->expand($customerSettings['fax']));
        $customer->setEmail($this->expand($customerSettings['email']));
        // @todo: overwriteIfExists: this is not collecting but completing.
        $customer->setOverwriteIfExists($customerSettings['overwriteIfExists']
            ? Api::OverwriteIfExists_Yes
            : Api::OverwriteIfExists_No);
        // @todo: bankAccountNumber?
        $this->expandAndSet($customer, Fld::Mark, $customerSettings['mark']);
        // @todo: disableDuplicates?

        return $customer;
    }
}
