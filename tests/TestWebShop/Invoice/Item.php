<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Invoice\Product;

/**
 * Item is the TestWebShop specific class to wrap an product item.
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        $this->shopObject = new \stdClass();
        $this->shopObject->id = $this->id;
        $this->shopObject->product_id = 1;
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->id;
    }

    protected function createProduct(): ?Product
    {
        $product = $this->shopObject->get_product();
        return $this->getContainer()->createProduct($this->shopObject->product_id, $this);
    }
}
