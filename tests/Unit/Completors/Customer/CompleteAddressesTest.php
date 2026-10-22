<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteAddressesTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteAddresses}.
 */
class CompleteAddressesTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public function createCustomerWithoutAddresses(): Customer
    {
        $customer = $this->getCustomer();
        $customer->contactId = 1;
        $customer->type = Api::CustomerType_Debtor;
        $customer->vatTypeId = Api::VatTypeId_Private;
        $customer->contactYourId = 2;
        $customer->contactStatus = Api::ContactStatus_Active;
        $customer->salutation = 'Mr Doe';
        $customer->website = 'https://example.com';
        $customer->vatNumber = null;
        $customer->telephone = '0123456789';
        $customer->telephone2 = '0612345789';
        $customer->fax = null;
        $customer->email = 'test@example.com';
        $customer->overwriteIfExists = true;
        $customer->bankAccountNumber = 'RABO123';
        $customer->mark = 'loyal';
        $customer->disableDuplicates = true;
        return $customer;
    }

    public function createAddress1(): Address
    {
        $address = $this->getAddress();
        $address->companyName1 = null;
        $address->companyName2 = null;
        $address->fullName = 'John Doe';
        $address->address1 = 'street 1';
        $address->address2 = 'building 2';
        $address->postalCode = '1234 AB';
        $address->city = 'City';
        $address->country = null;
        $address->countryCode = 'NL';
        $address->countryAutoName = Api::CountryAutoName_OnlyForeign;
        $address->countryAutoNameLang = 'NL';
        return $address;
    }

    public function createAddress2(): Address
    {
        $address = $this->createAddress1();
        $address->countryAutoName = Api::CountryAutoName_Yes;
        $address->countryAutoNameLang = 'EN';
        return $address;
    }

    public function createAddress3(): Address
    {
        $address = $this->getAddress();
        $address->address1 = 'straat 1';
        $address->postalCode = '1234AB';
        return $address;
    }

    public function createAddress4(): Address
    {
        $address = $this->getAddress();
        $address->fullName = 'jan';
        $address->postalCode = '1234AB';
        return $address;
    }

    private function createCustomer(Address $invoiceAddress, Address $shippingAddress, string $mainAddressType): Customer
    {
        $customer = $this->createCustomerWithoutAddresses();
        $customer->setMainAddressType($mainAddressType);
        $customer->setInvoiceAddress($invoiceAddress);
        $customer->setShippingAddress($shippingAddress);
        return $customer;
    }

    public function testCompleteAddresses(): void
    {
        $completor = self::getContainer()->getCompletorTask('Customer', 'Addresses');
        $invoiceAddress = $this->createAddress1();
        $shippingAddress = $this->createAddress2();
        $customer = $this->createCustomer($invoiceAddress, $shippingAddress, AddressType::Invoice);
        $completor->complete($customer);
        $altAddress = $customer->getShippingAddress();
        self::assertNull($altAddress);
//        self::assertNotSame($shippingAddress, $altAddress);
//        self::assertNotSame($shippingAddress->countryAutoName, $altAddress->countryAutoName);
    }
}
