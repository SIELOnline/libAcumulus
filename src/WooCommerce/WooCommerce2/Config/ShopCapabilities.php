<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Config;

use Siel\Acumulus\WooCommerce\Config\ShopCapabilities as ShopCapabilitiesBase;
use WC_Tax;

/**
 * Defines the WooCommerce2 webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * @inheritDoc
     */
    public function getVatClasses()
    {
        $keys = array_map( 'sanitize_title', WC_Tax::get_tax_classes());
        $labels = WC_Tax::get_tax_classes();
        return array_combine($keys, $labels);
    }
}
