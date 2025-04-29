<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
    private Container $container;

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
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $this->getContainer()->createAcumulusObject(DataType::Customer);
        return $customer;
    }

    public function customerConfigDataProvider(): array
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
        $config = $this->getContainer()->getConfig();
        $config->set($key, $value);
        $completor = $this->getContainer()->getCompletorTask('Customer','ByConfig');
        $customer = $this->getCustomer();
        $completor->complete($customer);
        /** @noinspection PhpVariableVariableInspection */
        $this->assertSame($expected, $customer->$property);
    }
}
