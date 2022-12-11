<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AcumulusProperty;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Token;

/**
 * Creates a {@see Customer} object
 */
class CustomerCollector extends Collector
{
    /** @noinspection PhpEnforceDocCommentInspection */
    public function __construct(Token $token)
    {
        parent::__construct(Customer::class, $token);
    }

    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
        $invoiceSource = $this->propertySources['invoiceSource'];

        // Identifying and status fields.
        // @todo: "contactId"?
        // @todo: "type": this is not collecting but completing.

        // Address 1.  @todo: separate address into own object, always collect both and decide if and how to use them in the completor.
        $acumulusObject->setCountryCode($invoiceSource->getCountryCode());
        // Add 'nl' as default country code.
        $acumulusObject->setCountryCode('nl', AcumulusProperty::Set_NotOverwrite);
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
        $acumulusObject->setOverwriteIfExists(
            $customerSettings['overwriteIfExists']
                ? Api::OverwriteIfExists_Yes
                : Api::OverwriteIfExists_No
        );
        // @todo: bankAccountNumber?
        // @todo: disableDuplicates?
    }
}
