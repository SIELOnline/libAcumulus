<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;

/**
 * Item is the VirtueMart specific class to wrap an order/refund item.
 *
 * @method object getShopObject() $shopObject
 * @property object $shopObject A virtuemart_order_items table record
 * @method null getProduct()
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in VirtueMart');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->virtuemart_order_item_id;
    }

    protected function createProduct(): ?Product
    {
        return null;
    }
}
