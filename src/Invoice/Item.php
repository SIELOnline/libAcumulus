<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Container;

/**
 * Item is an adapter (and wrapper) class around an item line of a web shop order or
 * refund.
 *
 * Item is used to provide unified access to information about an order or refund item
 * line from the web shop. Furthermore, by wrapping it in a single, library defined,
 * object type, web shop order and refund items can be passed around in a strongly typed
 * way.
 */
abstract class Item
{
    use WrapperTrait;

    /**
     * @var Source
     *   The parent Source for this Item.
     */
    protected Source $shopSource;

    /**
     * @var \Siel\Acumulus\Invoice\Product|null
     *   The product ordered on this item line.
     */
    protected ?Product $product;

    public function __construct(Source $shopSource, int|string|object|array|null $idOrItem, Container $container)
    {
        $this->shopSource = $shopSource;
        $this->initializeWrapper($idOrItem, $container);
    }

    public function getProduct(): ?Product
    {
        if (!isset($this->product)) {
            $this->product = $this->getShopProduct();
        }
        return $this->product;
    }

    /**
     * Returns the Product ordered on this Item.
     *
     * Overrides can use the parent $source and $this item to retrieve the Product.
     * If the product does no longer exists, null should be returned.
     * Normally, this method will be called only once by the public
     * method {@see getProduct()}, so it is correct to instantiate a new {@see Product}
     * object.
     */
    abstract protected function getShopProduct(): ?Product;
}
