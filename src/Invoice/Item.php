<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Product\Product;

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
    protected Source $source;

    /**
     * @var \Siel\Acumulus\Product\Product|null
     *   The product ordered on this item line.
     */
    protected ?Product $product;

    public function __construct(int|string|object|array|null $itemOrId, Source $source, Container $container)
    {
        $this->source = $source;
        $this->initializeWrapper($itemOrId, $container);
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getProduct(): ?Product
    {
        if (!isset($this->product)) {
            $this->product = $this->createProduct();
        }
        return $this->product;
    }

    /**
     * Creates a Product object representing the product ordered on this Item.
     *
     * Overrides can use the parent \$source and \$this item to retrieve the Product.
     * If the product does no longer exists, null should be returned.
     *
     * Normally, this method will be called only once by the public
     * method {@see getProduct()}, so it is correct to create a new instance.
     */
    abstract protected function createProduct(): ?Product;
}
