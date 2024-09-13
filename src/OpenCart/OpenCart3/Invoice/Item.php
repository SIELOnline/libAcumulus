<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

/**
 * Item is a wrapper/adapter around OpenCart specific order product lines.
 */
class Item extends \Siel\Acumulus\OpenCart\Invoice\Item
{
    public function getOrderProductOptions(): array
    {
        return $this->getOrderModel()->getOrderOptions($this->getShopObject()['order_id'], $this->getShopObject()['order_product_id']);
    }
}
