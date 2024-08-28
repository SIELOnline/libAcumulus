<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Invoice\Product;

/**
 * Item is the PrestaShop specific class to wrap an order/refund item.
 *
 * @property \OrderSlip $shopObject
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in PrestaShop');
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject['id_order_detail'];
    }

    /**
     * This override returns null as PrestaShop stores all relevant product data in the
     * item (an OrderDetail) itself, allowing to retrieve product data as it was at the
     * time of placing the order.
     */
    protected function getShopProduct(): ?Product
    {
        return null;
    }
}
