<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteDefaultsTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteDefaults}.
 */
class CompleteDefaultsTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

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
        $completor = self::getContainer()->getCompletorTask('Customer','Defaults');
        $customer = $this->getCustomer();
        $customer->setInvoiceAddress($this->getAddress());
        $customer->setShippingAddress($this->getAddress());
        $customer->getInvoiceAddress()->countryCode = $country;
        $customer->getShippingAddress()->countryCode = $country;
        $this->assertSame($expectedBefore, $customer->getInvoiceAddress()->countryCode);
        $this->assertSame($expectedBefore, $customer->getShippingAddress()->countryCode);
        $completor->complete($customer);
        $this->assertSame($expectedAfter, $customer->getInvoiceAddress()->countryCode);
        $this->assertSame($expectedAfter, $customer->getShippingAddress()->countryCode);
    }
}
