<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Invoice\Product;

/**
 * Item is a wrapper/adapter around OpenCart specific order product lines.
 *
 * @method object getShopObject()
 *   See {@see \hikashopOrder_productClass}
 * @property object $shopObject See {@see \hikashopOrder_productClass}
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
