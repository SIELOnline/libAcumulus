<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Product as BaseProduct;

/**
 * Product is a wrapper/adapter around a WooCommerce specific product (appearing on an Item).
 *
 * @property \WC_Product $shopObject
 * @method \WC_Product getShopObject()
 */
class Product extends BaseProduct
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WooCommerce');
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->get_id();
    }

//    public function getReference(): string
//    {
//        return $this->shopObject->get_sku();
//    }
//
//    public function getName(): string
//    {
//        return $this->shopObject->get_name();
//    }
//
//    public function getVatClass(): string
//    {
//        return $this->shopObject->get_tax_class();
//    }
}
