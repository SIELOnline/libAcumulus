<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Container;

/**
 * Product is an adapter (and wrapper) class around a product (variant) ordered (or
 * refunded) on an {@see Item item line} of a web shop order or refund.
 *
 * Product is used to provide unified access to information about a product or service
 * ordered or refunded on a web shop order or refund. Furthermore, by wrapping it in a
 * single, library defined, object type, web shop products can be passed around in a
 * strongly typed way.
 */
abstract class Product
{
    use WrapperTrait;

    /**
     * @var Item|null
     *   The {@see \Siel\Acumulus\Invoice\Item ittem line} on which the product appears or
     *   null if we are not in the context of an order.
     */
    protected ?Item $item;

    public function __construct(int|string|object|array|null $idOrProduct, ?Item $item, Container $container)
    {
        $this->item = $item;
        $this->initializeWrapper($idOrProduct, $container);
    }

    // @todo: Next phase: add methods that implement the adapter part: getReference(),
    //   getName(), etc. Actual implementations should be copied from
    //   ShopCapabilities::getDefaultShopMappings().
    /**
     * Returns the reference of the product.
     *
     * The reference is typically the SKU, ISBN, EAN13, or any other string used to
     * uniquely identify this product (variant).
     */
//    abstract public function getReference(): string;

    /**
     * Returns the name of the product.
     */
//    abstract public function getName(): string;

    /**
     * Returns the id of the vat class of the product.
     */
//    abstract public function getVatClassId(): int|string;

    /**
     * Returns the name of the vat class of the product.
     */
//    abstract public function getVatClassName(): string;
}
