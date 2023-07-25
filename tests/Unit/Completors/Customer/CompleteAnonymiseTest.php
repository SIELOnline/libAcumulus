<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteAnonymiseTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteAnonymise}.
 */
class CompleteAnonymiseTest extends TestCase
{
    private Container $container;

    public function createCustomer(): Customer
    {
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $this->getContainer()->createAcumulusObject(DataType::Customer);
        $customer->contactId = 1;
        $customer->type = Api::CustomerType_Debtor;
        $customer->vatTypeId = Api::VatTypeId_Private;
        $customer->contactYourId = 2;
        $customer->contactStatus = Api::ContactStatus_Active;
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
        $address = new Address();
        $address->companyName1 = null;
        $address->companyName2 = null;
        $address->fullName = 'John Doe';
        $address->salutation = 'Mr Doe';
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
        $address = new Address();
        $address->companyName1 = null;
        $address->companyName2 = null;
        $address->fullName = 'Mary Doe';
        $address->salutation = 'Ms Doe';
        $address->address1 = 'street 3';
        $address->address2 = 'building 4';
        $address->postalCode = '12345';
        $address->city = 'Place';
        $address->country = null;
        $address->countryCode = 'BE';
        $address->countryAutoName = Api::CountryAutoName_Yes;
        $address->countryAutoNameLang = 'BE';
        return $address;
    }

    public function createAnonymousCustomer(): Customer
    {
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $this->getContainer()->createAcumulusObject(DataType::Customer);
        $customer->contactId = null;
        $customer->type = null;
        $customer->vatTypeId = null;
        $customer->contactYourId = null;
        $customer->contactStatus = Api::ContactStatus_Disabled;
        $customer->website = null;
        $customer->vatNumber = null;
        $customer->telephone = null;
        $customer->telephone2 = null;
        $customer->fax = null;
        $customer->email = $this->getContainer()->getConfig()->get('genericCustomerEmail');
        $customer->overwriteIfExists = false;
        $customer->bankAccountNumber = null;
        $customer->mark = null;
        $customer->disableDuplicates = null;
        return $customer;
    }

    public function createAnonymousAddress1(): Address
    {
        $address = new Address();
        $address->companyName1 = null;
        $address->companyName2 = null;
        $address->fullName = null;
        $address->salutation = null;
        $address->address1 = null;
        $address->address2 = null;
        $address->postalCode = '1234 AB';
        $address->city = null;
        $address->country = null;
        $address->countryCode = 'NL';
        $address->countryAutoName = null;
        $address->countryAutoNameLang = null;
        return $address;
    }

    public function createAnonymousAddress2(): Address
    {
        $address = new Address();
        $address->companyName1 = null;
        $address->companyName2 = null;
        $address->fullName = null;
        $address->salutation = null;
        $address->address1 = null;
        $address->address2 = null;
        $address->postalCode = '12345';
        $address->city = null;
        $address->country = null;
        $address->countryCode = 'BE';
        $address->countryAutoName = null;
        $address->countryAutoNameLang = null;
        return $address;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->container->addTranslations('Translations', 'Invoice');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getCustomer(): Customer
    {
        $customer = $this->createCustomer();
        $customer->setInvoiceAddress($this->createAddress1());
        $customer->setShippingAddress($this->createAddress2());
        return $customer;
    }

    private function getAnonymousCustomer(): Customer
    {
        $customer = $this->createAnonymousCustomer();
        $customer->setInvoiceAddress($this->createAnonymousAddress1());
        $customer->setShippingAddress($this->createAnonymousAddress1());
        return $customer;
    }

    public function testCompleteDoNotAnonymise(): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('sendCustomer', true);
        $completor = $this->getContainer()->getCompletorTask('Customer', 'Anonymise');
        $customer = $this->getCustomer();
        $customerBefore = $customer->toArray();
        $completor->complete($customer);
        $customerAfter = $customer->toArray();
        $this->assertEqualsCanonicalizing($customerBefore, $customerAfter);
    }

    public function testCompleteAnonymise(): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('sendCustomer', false);
        $completor = $this->getContainer()->getCompletorTask('Customer', 'Anonymise');
        $customer = $this->getCustomer();
        $anonymousCustomer = $this->getAnonymousCustomer();
        $completor->complete($customer);
        $customerAfter = $customer->toArray();
        $this->assertEqualsCanonicalizing($anonymousCustomer->toArray(), $customerAfter);
    }

    public function testCompleteAnonymiseCompany(): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('sendCustomer', false);
        $completor = $this->getContainer()->getCompletorTask('Customer', 'Anonymise');
        $customer = $this->getCustomer();
        $customer->vatTypeId = Api::VatTypeId_Business;
        $customer->vatNumber = 'NL123456789';
        $customer->getInvoiceAddress()->companyName1 = 'Company 1';
        $customer->getInvoiceAddress()->companyName2 = 'Company 2';
        $customer->getShippingAddress()->companyName1 = 'Company 3';
        $customer->getShippingAddress()->companyName2 = 'Company 4';
        $customerBefore = $customer->toArray();
        $completor->complete($customer);
        $customerAfter = $customer->toArray();
        $this->assertEqualsCanonicalizing($customerBefore, $customerAfter);
    }


}
