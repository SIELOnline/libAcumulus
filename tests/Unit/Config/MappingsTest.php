<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Config;

use Siel\Acumulus\Config\Mappings;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * MappingsTest tests the {@see \Siel\Acumulus\Config\Mappings} class.
 */
class MappingsTest extends TestCase
{
    private const Language = 'en';

    private static Container $container;

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = new Container('TestWebShop', self::Language);
        }
        return self::$container;
    }

    private function getMappings(): Mappings
    {
        return $this->getContainer()->getMappings();
    }

    public function testGetOverriddenValues(): void
    {
        /** @var \Siel\Acumulus\TestWebShop\Config\Mappings $mappings */
        $mappings = $this->getMappings();

        $defaults = [
            DataType::Invoice => [
                Fld::PaymentStatus => '[source::getPaymentStatus()]',
                Fld::PaymentDate => '[source::getPaymentDate()]',
                Fld::Description => true,
                Meta::SourceType => true,
                Meta::Id => false,
                Meta::SourceReference => false,
                Meta::SourceDate => 'true',
                Meta::SourceStatus => 'true',
                Meta::PaymentMethod => 2,
                Meta::ShopInvoiceId => 2,
                Meta::ShopInvoiceReference => '2',
                Meta::ShopInvoiceDate => '2',
                Meta::Currency => 3.5,
                Meta::Totals => '3.5',
            ],
            AddressType::Invoice => [
                Fld::CountryCode => '3.5',
            ],
        ];
        $final = [
            DataType::Invoice => [
                Fld::PaymentStatus => '[source::getPaymentStatus()]',
                Fld::PaymentDate => 'overridden',
                Fld::Description => 'true',
                Meta::SourceType => 'false',
                Meta::Id => 'false',
                Meta::SourceReference => 'true',
                Meta::SourceDate => 'true',
                Meta::SourceStatus => 'false',
                Meta::PaymentMethod => '2',
                Meta::ShopInvoiceId => '3',
                Meta::ShopInvoiceReference => '2',
                Meta::ShopInvoiceDate => '3',
                Meta::Currency => '3.5',
                Meta::Totals => '3.5',
            ],
            AddressType::Invoice => [
                Fld::CountryCode => '4.5',
            ],
        ];
        $expected = [
            DataType::Invoice => [
                Fld::PaymentDate => 'overridden',
                Meta::SourceType => 'false',
                Meta::SourceReference => 'true',
                Meta::SourceStatus => 'false',
                Meta::ShopInvoiceId => '3',
                Meta::ShopInvoiceDate => '3',
            ],
            AddressType::Invoice => [
                Fld::CountryCode => '4.5',
            ],
        ];
        $actual = $mappings->getOverriddenValues($final, $defaults);
        $this->assertSame($expected, $actual);
    }
}
