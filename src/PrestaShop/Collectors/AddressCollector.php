<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Context;
use Country;
use Siel\Acumulus\Collectors\AddressCollector as BaseAddressCollector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

use function is_array;

/**
 * AddressCollector for PrestaShop.
 */
class AddressCollector extends BaseAddressCollector
{
    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $shopCountryId = $acumulusObject->metadataGet(Meta::ShopCountryId);
        if (!empty($shopCountryId)) {
            $country = new Country($shopCountryId);
            $acumulusObject->countryCode = $country->iso_code;

            $languageId = Context::getContext()->language->getId();
            if (is_array($country->name)) {
                if (!empty($country->name[$languageId])) {
                    $name = $country->name[$languageId];
                } else {
                    $name = reset($country->name);
                }
            } else {
                $name = $country->name;
            }
            $acumulusObject->metadataSet(Meta::ShopCountryName, $name);
        }
    }
}
