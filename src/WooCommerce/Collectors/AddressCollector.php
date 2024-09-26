<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Collectors\AddressCollector as BaseAddressCollector;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * AddressCollector for WooCommerce.
 */
class AddressCollector extends BaseAddressCollector
{
    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        if (!empty($acumulusObject->countryCode)) {
            /** @var \WooCommerce $woocommerce */
            global $woocommerce;
            $countries = $woocommerce->countries->get_countries();
            $acumulusObject->metadataSet(Meta::ShopCountryName, $countries[$acumulusObject->countryCode] ?? null);
        }
    }
}
