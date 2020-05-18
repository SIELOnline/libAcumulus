<?php
namespace Siel\Acumulus\TestWebShop\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the TestWebShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        return [
            'moduleVersion' => '4.0',
            'shopName' => 'TestWebShop',
            'shopVersion' => '0.1',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoSource()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoShopProperties()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        return [];
    }

    public function getVatClasses()
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function getLink($linkType)
    {
        return '';
    }
}
