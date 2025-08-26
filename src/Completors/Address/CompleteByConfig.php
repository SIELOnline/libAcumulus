<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Address;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteByConfig adds configuration-based values.
 */
class CompleteByConfig extends BaseCompletorTask
{
    /**
     * Adds some values based on configuration.
     *
     * The following fields are set based on their corresponding config value:
     * - CountryAutoName and optionally country.
     *
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $countryAutoName = $this->configGet('countryAutoName');
        switch ($countryAutoName) {
            case Api::CountryAutoName_No:
            case Api::CountryAutoName_OnlyForeign:
            case Api::CountryAutoName_Yes:
                $acumulusObject->setCountryAutoName($countryAutoName, PropertySet::NotOverwrite);
                break;
            case Config::Country_ForeignFromShop:
            case Config::Country_FromShop:
                $acumulusObject->setCountryAutoName(Api::CountryAutoName_No, PropertySet::NotOverwrite);
                $countryName = $countryAutoName === Config::Country_ForeignFromShop && strtoupper($acumulusObject->countryCode) === 'NL'
                    ? ''
                    : $acumulusObject->metadataGet(Meta::ShopCountryName);
                $acumulusObject->setCountry($countryName, PropertySet::NotOverwrite);
                break;
            default:
                assert(false, __METHOD__ . ": setting 'countryAutoName' has an unknown value $countryAutoName");
        }
    }
}
