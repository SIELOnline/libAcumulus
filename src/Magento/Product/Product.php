<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;

/**
 * Product is a wrapper/adapter around a Magento specific product (appearing on an Item).
 *
 * @property \Magento\Catalog\Model\Product $shopObject
 * @method \Magento\Catalog\Model\Product getShopObject()
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in Magento');
    }

    protected function setId(): void
    {
        /** @noinspection PhpCastIsUnnecessaryInspection  actually a numeric string is returned */
        $this->id = (int) $this->shopObject->getId();
    }
}
