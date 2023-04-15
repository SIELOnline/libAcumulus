<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * AddressCollector for WooCommerce.
 */
class AddressCollector extends \Siel\Acumulus\Collectors\AddressCollector
{
    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        if (!empty($acumulusObject->countryCode)) {
            global $woocommerce;
            $acumulusObject->metadataSet(
                Meta::ShopCountryName,
                $woocommerce->countries->get_countries()[$acumulusObject->countryCode] ?? null
            );
        }
    }
}
