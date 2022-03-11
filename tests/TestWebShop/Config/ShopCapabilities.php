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
    public function getShopEnvironment(): array
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
    protected function getTokenInfoSource(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoShopProperties(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        return [];
    }

    public function getVatClasses(): array
    {
        return [];
    }


    /**
     * {@inheritdoc}
     */
    public function getLink($linkType): string
    {
        return '';
    }
}
