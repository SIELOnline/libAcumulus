<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;
use stdClass;

/**
 * Item is the TestWebShop specific class to wrap a product item line.
 */
class Item extends BaseItem
{
    private static array $productIds = [
        3 => 13,
        4 => 14,
        5 => 15,
        6 => 16,
        7 => 17,
        8 => 18,
    ];

    protected function setShopObject(): void
    {
        $this->shopObject = new stdClass();
        $this->shopObject->id = $this->id;
        $this->shopObject->product_id = self::$productIds[$this->id] ?? 1;
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->id;
    }

    protected function createProduct(): ?Product
    {
        return $this->getContainer()->createProduct($this->shopObject->product_id, $this);
    }
}
