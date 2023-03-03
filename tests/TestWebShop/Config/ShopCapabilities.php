<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the TestWebShop specific capabilities.
 *
 * For now, we only have a minimal implementation.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
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

    public function getDefaultShopConfig(): array
    {
        return [];
    }

    public function getShopOrderStatuses(): array
    {
        return [];
    }

    public function getPaymentMethods(): array
    {
        return [];
    }

    public function getVatClasses(): array
    {
        return [];
    }
}
