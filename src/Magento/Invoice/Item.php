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
    // @todo: Are child items considered to be Magento specific or is this to be
    //   generalised for all shops (even those that do not know that concept).
    //   If so, should we extract this into a trait to be used by Line and Item?
    /** @var ?Item */
    protected self|null $parent = null;
    /** @var Item[] */
    protected array $children = [];

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
        // The reference to an order item in a Credit memo item should refer to an
        // existing order item, but if not, considered database corruption, we continue
        // and the library will try to complete the line.
        $shopProduct = $this->getSource()->getType() === Source::Order
            ? $this->getShopObject()->getProduct()
            : $this->getShopObject()->getOrderItem()?->getProduct();
        return $shopProduct !== null ? $this->getContainer()->createProduct($shopProduct, $this) : null;
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }

    public function getParent(): ?Item
    {
        return $this->parent;
    }

    public function hasChildren(): bool
    {
        return count($this->getChildren()) > 0;
    }

    /**
     * @return static[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Item $child): void
    {
        $this->children[] = $child;
        $child->parent = $this;
    }
}
