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
        $labels = WC_Tax::get_tax_classes();
        $keys = array_map( 'sanitize_title', $labels);
        return array_combine($keys, $labels);
    }
}
