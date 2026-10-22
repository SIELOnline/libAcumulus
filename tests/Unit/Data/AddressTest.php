<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * AddressTest tests {@see \Siel\Acumulus\Data\Address}.
 */
class AddressTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

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

    public function testEquals(): void
    {
        $address1 = $this->createAddress1();
        self::assertFalse($address1->equals(null));
        self::assertTrue($address1->equals($address1));
        self::assertTrue($address1->equals($this->createAddress2()));
        self::assertFalse($address1->equals($this->createAddress3()));
    }

    public function testEmpty(): void
    {
        self::assertFalse($this->createAddress1()->isEmpty());
        $address3 = $this->createAddress3();
        self::assertTrue($address3->isEmpty());
        $address3->setAddressType(AddressType::Invoice);
        self::assertTrue($address3->isEmpty());
        $address3->setAddressType(AddressType::Shipping);
        self::assertFalse($address3->isEmpty());
        $address3->setAddressType(AddressType::Store);
        self::assertTrue($address3->isEmpty());

        $address4 = $this->createAddress4();
        self::assertTrue($address4->isEmpty());
        $address4->setAddressType(AddressType::Invoice);
        self::assertFalse($address4->isEmpty());
        $address4->setAddressType(AddressType::Shipping);
        self::assertTrue($address4->isEmpty());
        $address4->setAddressType(AddressType::Store);
        self::assertTrue($address4->isEmpty());
    }
}
