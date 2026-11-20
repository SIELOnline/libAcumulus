<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;
use Siel\Acumulus\Magento\Helpers\Registry;

/**
 * Item is a wrapper/adapter around Magento specific order/credit memo product lines.
 *
 * @method \Siel\Acumulus\Magento\Product\Product|null getProduct()
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
        $this->id = (int) $this->getShopObject()->getId();
    }

    protected function createProduct(): ?Product
    {
        $shopProduct = $this->getSource()->getType() === Source::Order
            ? $this->shopObject->getProduct()
            : $this->getShopObject()->getOrderItem()->getProduct();
        return $shopProduct !== null ? $this->getContainer()->createProduct($shopProduct, $this) : null;
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
