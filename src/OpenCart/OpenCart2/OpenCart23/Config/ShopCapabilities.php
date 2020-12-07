<?php
namespace Siel\Acumulus\OpenCart\OpenCart23\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the OpenCart2.3 webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function hasInvoiceStatusScreen()
    {
        return false;
    }
}
