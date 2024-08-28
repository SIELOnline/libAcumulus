<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Container;

/**
 * Product is an adapter (and wrapper) class around a product (or service) ordered (or
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
     * @var Item
     *   The parent item line for this Product.
     */
    protected Item $item;

    public function __construct(Item $item, int|string|object|array|null $idOrProduct, Container $container)
    {
        $this->item = $item;
        $this->initializeWrapper($idOrProduct, $container);
    }

    abstract public function getReference(): string;

    abstract public function getName(): string;

    abstract public function getVatClass(): string;
}
