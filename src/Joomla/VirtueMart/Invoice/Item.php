<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

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
