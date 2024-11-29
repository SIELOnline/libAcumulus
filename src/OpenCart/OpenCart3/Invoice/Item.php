<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Item as BaseItem;

/**
 * Item is a wrapper/adapter around OpenCart specific order product lines.
 */
class Item extends BaseItem
{
    public function getOrderProductOptions(): array
    {
        return $this->getOrderModel()->getOrderOptions($this->getShopObject()['order_id'], $this->getShopObject()['order_product_id']);
    }
}
