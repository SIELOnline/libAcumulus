<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Address;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Address\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public static function addressConfigDataProvider(): array
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
        $config = self::getContainer()->getConfig();
        $config->set($key, $value);
        $completor = self::getContainer()->getCompletorTask('Address','ByConfig');
        $address = $this->getAddress();
        $address->countryCode = $countryCode;
        $address->metadataSet(Meta::ShopCountryName, $shopCountryName);

        $completor->complete($address);

        self::assertSame(strtoupper($countryCode), $address->countryCode);
        self::assertSame($expectedCountryAutoName, $address->countryAutoName);
        self::assertSame($expectedCountry, $address->country);
    }
}
