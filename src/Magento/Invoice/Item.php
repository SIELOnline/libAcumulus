<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use Magento\Catalog\Model\Product as MagentoProduct;
use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Invoice\Product;
use Siel\Acumulus\Magento\Helpers\Registry;

/**
 * Item is a wrapper/adapter around Magento specific order/credit memo product lines.
 *
 * @method \Siel\Acumulus\Magento\Invoice\Product getProduct()
 * @method \Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Creditmemo\Item getShopObject()
 * @property \Magento\Sales\Model\Order\Item|\Magento\Sales\Model\Order\Creditmemo\Item $shopObject
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in Magento');
    }

    protected function setId(): void
    {
        /** noinspection PhpCastIsUnnecessaryInspection  actually a numeric string is returned */
        $this->id = (int) $this->getShopObject()->getId();
    }

    protected function createProduct(): ?Product
    {
        if ($this->getSource()->getType() === Source::Order) {
            $product = $this->shopObject->getProduct();
            return $product !== null ? $this->getContainer()->createProduct($this, $product) : null;
        } else {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->getRegistry()->create(MagentoProduct::class);
            $this->getRegistry()->get($product->getResourceName())->load($product, $this->shopObject->getProductId());
            return !empty($product->getId()) ? $this->getContainer()->createProduct($this, $product) : null;

        }
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
