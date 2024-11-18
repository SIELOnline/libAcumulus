<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;

use function get_class;
use function sprintf;

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
    public function getReference(): string
    {
        // @todo: use Mappings + FieldExpander?
        throw new RuntimeException(sprintf('%s::%s(): Not implemented', get_class(), __FUNCTION__));
    }

    public function getReferenceForAcumulusLookup(): string
    {
        // @todo: define config option that defines which field to use and retrieve that field
        throw new RuntimeException(sprintf('%s::%s(): Not implemented', get_class(), __FUNCTION__));
    }

    /**
     * Returns the human-readable name of the product.
     */
    public function getName(): string
    {
        throw new RuntimeException(sprintf('%s::%s(): Not implemented', get_class(), __FUNCTION__));
    }

    /**
     * Returns the id of the vat class of the product.
     */
//    abstract public function getVatClassId(): int|string;

    /**
     * Returns the name of the vat class of the product.
     */
//    abstract public function getVatClassName(): string;

    /**
     * Returns the {@see Product} on which the stock is managed for this product.
     *
     * This will typically be the product itself, but in case of variants that do not need
     * separate stock, it may be the parent product. Note: that this is probably a sign of
     * misconfiguration, as few are products with variants for which all variants ship the
     * same parent product (electricity cables with some predefined lengths as variants
     * and cut on order, though normally you wil have prepackaged lengths of 10, 25, 50
     * and 100m.)
     */
    public function getStockManagingProduct(): static
    {
        return $this;
    }
}
