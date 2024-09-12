<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Product;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Item is a wrapper/adapter around OpenCart specific order product lines.
 */
class Item extends \Siel\Acumulus\Invoice\Item
{
    /**
     * @inheritDoc
     */
    protected function getShopProduct(): ?Product
    {
        // $product can be empty if the product has been deleted.
        /** @var \Opencart\Admin\Model\Catalog\Product|\ModelCatalogProduct $model */
        $model = Registry::getInstance()->getModel('catalog/product');
        $product = $model->getProduct($this->getShopObject()['product_id']);
        return !empty($product) ? $this->getContainer()->createProduct($this, $product) : null;
    }

    /**
     * @inheritDoc
     */
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in OpenCart');
    }

    /**
     * @inheritDoc
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()['order_product_id'];
    }
}
