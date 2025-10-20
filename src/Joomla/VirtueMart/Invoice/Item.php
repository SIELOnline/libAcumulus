<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;
use stdClass;

/**
 * Item is the VirtueMart specific class to wrap an order/refund item.
 *
 * @method object getShopObject() $shopObject
 * @property object $shopObject A virtuemart_order_items table record
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in VirtueMart');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->virtuemart_order_item_id;
    }

    /**
     * {@inheritdoc}
     *
     * VirtueMart stores all "relevant" product data in the item (an order_item) itself,
     * allowing to retrieve product data as it was at the time of placing the order. So,
     * preferably use these fields. However, exactly what should be considered relevant
     * varies from use case to use case, therefore we do load the product record and
     * return it, so data that was not put in the order_item table can still be accessed.
     *
     * It may still return null if not found (no longer available), or if
     * virtuemart_product_id is empty to begin with.
     */
    protected function createProduct(): ?Product
    {
        $product_id = $this->getShopObject()->virtuemart_product_id;
        return !empty($product_id) ? $this->getContainer()->createProduct($product_id, $this) : null;
    }
}
