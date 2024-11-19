<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;

/**
 * Item is the HikaShop specific class to wrap an order/refund item.
 *
 * @method object getShopObject()
 * @property object $shopObject See {@see \hikashopOrder_productClass}
 * @method null getProduct()
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in HikaShop');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->order_product_id;
    }

    protected function createProduct(): ?Product
    {
        return null;
    }
}
