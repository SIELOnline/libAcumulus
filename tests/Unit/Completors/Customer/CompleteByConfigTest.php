<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
    use AcumulusContainer;

    private function getCustomer(): Customer
    {
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = self::getContainer()->createAcumulusObject(DataType::Customer);
        return $customer;
    }

    public static function customerConfigDataProvider(): array
    {
        return [
            ['defaultCustomerType', Api::CustomerType_Debtor, 'type', Api::CustomerType_Debtor],
            ['defaultCustomerType', Api::CustomerType_Creditor, 'type', Api::CustomerType_Creditor],
            ['defaultCustomerType', Api::CustomerType_Relation, 'type', Api::CustomerType_Relation],
            ['contactStatus', Api::ContactStatus_Disabled, 'contactStatus', false],
            ['contactStatus', Api::ContactStatus_Active, 'contactStatus', true],
            ['overwriteIfExists', Api::OverwriteIfExists_No, 'overwriteIfExists', false],
            ['overwriteIfExists', Api::OverwriteIfExists_Yes, 'overwriteIfExists', true],
            ['disableDuplicates', Api::DisableDuplicates_No, 'disableDuplicates', false],
            ['disableDuplicates', Api::DisableDuplicates_Yes, 'disableDuplicates', true],
        ];
    }

    /**
     * @dataProvider customerConfigDataProvider
     */
    public function testComplete(string $key, int $value, string $property, bool|int $expected): void
    {
        $config = self::getContainer()->getConfig();
        $config->set($key, $value);
        $completor = self::getContainer()->getCompletorTask('Customer','ByConfig');
        $customer = $this->getCustomer();
        $completor->complete($customer);
        /** @noinspection PhpVariableVariableInspection */
        $this->assertSame($expected, $customer->$property);
    }
}
