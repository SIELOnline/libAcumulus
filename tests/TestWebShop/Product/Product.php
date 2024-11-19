<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Product;

use Siel\Acumulus\Product\Product as BaseProduct;
use stdClass;

/**
 * Product is the TestWebShop specific class to wrap a product appearing on an item line.
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        $this->shopObject = new stdClass();
        $this->shopObject->id = $this->id;
        $this->shopObject->name = 'Black Bag';
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->id;
    }
}
