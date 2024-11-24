<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Item is the WooCommerce specific class to wrap an order/refund item.
 *
 * @property WC_Order_Item_Product $shopObject
 * @method WC_Order_Item_Product getShopObject()
 * @method \Siel\Acumulus\WooCommerce\Product\Product getProduct()
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WooCommerce');
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->get_id();
    }

    /**
     * This WooCommerce override wraps a {@see WC_Product} in a Product but may return
     * null when the product does no longer exist.
     */
    protected function createProduct(): ?Product
    {
        $product = $this->shopObject->get_product();
        return $product instanceof WC_Product ? $this->getContainer()->createProduct($product, $this) : null;
    }
}
