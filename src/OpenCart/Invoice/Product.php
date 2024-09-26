<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Product as BaseProduct;

/**
 * Product is a wrapper/adapter around an OpenCart specific product (appearing on an Item).
 *
 * @property array $shopObject
 * @method array getShopObject()
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in OpenCart');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()['product_id'];
    }
}
