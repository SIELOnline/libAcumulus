<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Token;

class CollectCustomer extends Collect
{
    protected function createAcumulusObject(): AcumulusObject
    {
        return new Customer();
    }

    /**
     * @param \Siel\Acumulus\Invoice\Customer $customer
     */
    public function collectLogicFields(AcumulusObject $customer)
    {

        /** @var Source $invoiceSource */
        $invoiceSource = $this->propertySources['invoiceSource'];

        // Identifying and status fields.
        // @todo: "contactId"?
        // @todo: "type": this is not collecting but completing.

        // Address 1.  @todo: separate address into own object, always collect both and decide if and how to use them in the completor.
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
        // @todo: telephone2? (use fax?)
        // @todo: overwriteIfExists: this is not collecting but completing.
        $customer->setOverwriteIfExists($customerSettings['overwriteIfExists']
            ? Api::OverwriteIfExists_Yes
            : Api::OverwriteIfExists_No);
        // @todo: bankAccountNumber?
        // @todo: disableDuplicates?
    }
}
