<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteDefaultsTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteDefaults}.
 */
class CompleteDefaultsTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
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
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $this->getContainer()->createAcumulusObject(DataType::Customer);
        return $customer;
    }

    public static function customerConfigDataProvider(): array
    {
        return [
            ['', null, 'NL'],
            [null, null, 'NL'],
            ['BE', 'BE', 'BE'],
        ];
    }

    /**
     * @dataProvider customerConfigDataProvider
     */
    public function testComplete(?string $country, ?string $expectedBefore, string $expectedAfter): void
    {
        $completor = $this->getContainer()->getCompletorTask('Customer','Defaults');
        $customer = $this->getCustomer();
        $customer->setInvoiceAddress(new Address());
        $customer->setShippingAddress(new Address());
        $customer->getInvoiceAddress()->countryCode = $country;
        $customer->getShippingAddress()->countryCode = $country;
        $this->assertSame($expectedBefore, $customer->getInvoiceAddress()->countryCode);
        $this->assertSame($expectedBefore, $customer->getShippingAddress()->countryCode);
        $completor->complete($customer);
        $this->assertSame($expectedAfter, $customer->getInvoiceAddress()->countryCode);
        $this->assertSame($expectedAfter, $customer->getShippingAddress()->countryCode);
    }
}
