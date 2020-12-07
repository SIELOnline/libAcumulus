<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the OpenCart1 webshop specific capabilities.
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
