<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;

use function assert;

/**
 * CompleteAnonymiseClient completes the {@see \Siel\Acumulus\Data\Customer::$template}
 * property of an {@see \Siel\Acumulus\Data\Customer}.
 *
 * @todo: does this option work well with counting EU tax?
 * @todo: may we anonymise vat type = 6 invoices?
 */
class CompleteAnonymise extends BaseCompletorTask
{
    /**
     * Removes customer data (if set so).
     *
     * If you have lots of one-time customers, you may not want to add the
     * customer data to your administration but instead link all those orders to
     * one fictitious client.
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     * @param int ...$args
     *   Additional parameters: none
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Customer);
        $sendCustomer = $this->configGet('sendCustomer');
        if (!$sendCustomer && !$acumulusObject->isCompany()) {
            // Create an address with only the country and postal code set, we may need
            // the latter to distinguish XI from GB
            $countryCode = $acumulusObject->getFiscalAddress()->countryCode;
            $postalCode = $acumulusObject->getFiscalAddress()->postalCode;
            /** @var \Siel\Acumulus\Data\Address $anonymousAdress */
            $anonymousAdress = $this->getContainer()->createAcumulusObject(DataType::Address);
            $anonymousAdress->countryCode = $countryCode;
            $anonymousAdress->postalCode = $postalCode;
            //  ... and use this address for both addresses.
            $acumulusObject->setInvoiceAddress($anonymousAdress);
            $acumulusObject->setShippingAddress($anonymousAdress);
            // Unset most Customer properties.
            unset(
                $acumulusObject->contactId,
                $acumulusObject->type,
                $acumulusObject->vatTypeId,
                $acumulusObject->contactYourId,
                $acumulusObject->salutation,
                $acumulusObject->website,
                $acumulusObject->vatNumber,
                $acumulusObject->telephone,
                $acumulusObject->telephone2,
                $acumulusObject->fax,
                $acumulusObject->bankAccountNumber,
                $acumulusObject->mark,
                $acumulusObject->disableDuplicates,
            );
            // Except the identifying e-mail address and some statuses.
            $acumulusObject->email = $this->configGet('genericCustomerEmail');
            $acumulusObject->contactStatus = Api::ContactStatus_Disabled;
            $acumulusObject->overwriteIfExists = Api::OverwriteIfExists_No;
            $acumulusObject->disableDuplicates = Api::DisableDuplicates_No;
        }
    }
}
