<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;
use TableProducts;
use VmModel;

/**
 * @property TableProducts $shopObject
 * @method TableProducts getShopObject()
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        $this->shopObject = VmModel::getModel('product')->getProduct($this->id);
    }

    protected function setId(): void
    {
        throw new RuntimeException('This method is not expected to be called in HikaShop');
    }
}
