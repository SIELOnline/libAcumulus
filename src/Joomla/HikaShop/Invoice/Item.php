<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopOrder_productClass;
use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;

/**
 * Item is the HikaShop specific class to wrap an order/refund item.
 *
 * @method hikashopOrder_productClass getShopObject()
 * @property hikashopOrder_productClass $shopObject
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in HikaShop');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->order_product_id;
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop stores all "relevant" product data in the item (an order_product) itself,
     * allowing to retrieve product data as it was at the time of placing the order. So,
     * preferably use these fields. However, exactly what should be considered relevant
     * varies from use case to use case, therefore we do load the product record and
     * return it, so data that was not put in the order_product table can still be
     * accessed.
     *
     * It may still return null if not found (no longer available), or if product_id is
     * empty to begin with.
     */
    protected function createProduct(): ?Product
    {
        $product_id = $this->getShopObject()->product_id;
        return !empty($product_id) ? $this->getContainer()->createProduct($product_id, $this) : null;
    }
}
