<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;

/**
 * @property \hikashopProductClass $shopObject
 * @method \hikashopProductClass getShopObject()
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        /** @var \hikashopProductClass $productClass */
        $productClass = hikashop_get('class.product');
        $this->shopObject = $productClass->get($this->id);
    }

    protected function setId(): void
    {
        throw new RuntimeException('This method is not expected to be called in HikaShop');
    }
}
