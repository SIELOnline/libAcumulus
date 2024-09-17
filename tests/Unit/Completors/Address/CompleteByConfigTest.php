<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Address;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Address\CompleteByConfig}.
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

    private function getAddress(): Address
    {
        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $this->getContainer()->createAcumulusObject(DataType::Address);
        return $address;
    }

    public function addressConfigDataProvider(): array
    {
        return [
            ['countryAutoName', Api::CountryAutoName_No, 'NL', 'Nederland', Api::CountryAutoName_No, null],
            ['countryAutoName', Api::CountryAutoName_Yes, 'nl', 'Nederland', Api::CountryAutoName_Yes, null],
            ['countryAutoName', Api::CountryAutoName_OnlyForeign, 'Nl', 'Nederland', Api::CountryAutoName_OnlyForeign, null],
            ['countryAutoName', Config::Country_FromShop, 'NL', 'Nederland', Api::CountryAutoName_No, 'Nederland'],
            // @todo: change to '' when "clearing" values is supported.
            ['countryAutoName', Config::Country_ForeignFromShop, 'nl', 'Nederland', Api::CountryAutoName_No, null],

            ['countryAutoName', Api::CountryAutoName_No, 'FR', 'Frankrijk', Api::CountryAutoName_No, null],
            ['countryAutoName', Api::CountryAutoName_Yes, 'fr', 'Frankrijk', Api::CountryAutoName_Yes, null],
            ['countryAutoName', Api::CountryAutoName_OnlyForeign, 'Fr', 'Frankrijk', Api::CountryAutoName_OnlyForeign, null],
            ['countryAutoName', Config::Country_FromShop, 'FR', 'Frankrijk', Api::CountryAutoName_No, 'Frankrijk'],
            ['countryAutoName', Config::Country_ForeignFromShop, 'fr', 'Frankrijk', Api::CountryAutoName_No, 'Frankrijk'],
        ];
    }

    /**
     * @dataProvider addressConfigDataProvider
     */
    public function testComplete(
        string $key,
        ?int $value,
        ?string $countryCode,
        ?string $shopCountryName,
        ?int $expectedCountryAutoName,
        ?string $expectedCountry
    ): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set($key, $value);
        $completor = $this->getContainer()->getCompletorTask('Address','ByConfig');
        $address = $this->getAddress();
        $address->countryCode = $countryCode;
        $address->metadataSet(Meta::ShopCountryName, $shopCountryName);

        $completor->complete($address);

        $this->assertSame(strtoupper($countryCode), $address->countryCode);
        $this->assertSame($expectedCountryAutoName, $address->countryAutoName);
        $this->assertSame($expectedCountry, $address->country);
    }
}
