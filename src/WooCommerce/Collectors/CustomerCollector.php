<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Meta;

/**
 * CustomerCollector for WooCommerce.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $taxBasedOn = get_option('woocommerce_tax_based_on');
        $taxBasedOnMapping = [
            'shipping' => AddressType::Shipping,
            'billing' => AddressType::Invoice,
            'base' => null,
        ];
        $acumulusObject->metadataSet(Meta::TaxBasedOn, $taxBasedOn);
        $acumulusObject->setTaxBasedOn($taxBasedOnMapping[$taxBasedOn] ?? null);
    }

}