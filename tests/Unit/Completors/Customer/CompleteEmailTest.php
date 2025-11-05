<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Customer;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * CompleteEmailTest tests {@see \Siel\Acumulus\Completors\Customer\CompleteEmail}.
 */
class CompleteEmailTest extends TestCase
{
    use AcumulusContainer;

    private function getCustomer(): Customer
    {
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = self::getContainer()->createAcumulusObject(DataType::Customer);
        return $customer;
    }

    public static function customerEmailDataProvider(): array
    {
        return [
            ['burorader@example.com', 'burorader@example.com', false],
            ['Buro RaDer <burorader@example.com>', 'burorader@example.com', false],
            ['burorader@example.com, support@example.com', 'burorader@example.com', false],
            ['Buro RaDer <burorader@example.com>, support@example.com', 'burorader@example.com', false],
            ['burorader@example.com, Buro RaDer <support@example.com>', 'burorader@example.com', false],
            ['Buro RaDer <burorader@example.com>, Buro RaDer <support@example.com>', 'burorader@example.com', false],
            ['Buro RaDer <burorader@example.com>; Buro RaDer <support@example.com>', 'burorader@example.com', false],
            ['', '', true],
        ];
    }

    /**
     * @dataProvider customerEmailDataProvider
     */
    public function testComplete(string $email, string $expected, bool $expectWarning): void
    {
        if ($expected === '') {
            $config = self::getContainer()->getConfig();
            $expected = $config->get('emailIfAbsent');
        }
        $completor = self::getContainer()->getCompletorTask('Customer','Email');
        $customer = $this->getCustomer();
        $customer->email = $email;
        $completor->complete($customer);
        $this->assertSame($expected, $customer->email);
        $this->assertSame($expectWarning, $customer->hasWarning());
    }
}
