<?php

declare(strict_types=1);

namespace Siel\Acumulus\Product;

use RuntimeException;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\WrapperTrait;

use function get_class;
use function sprintf;

/**
 * Product is an adapter (and wrapper) class around a product (variant).
 *
 * It will often be used as an ordered (or refunded) product on a
 * {@see \Siel\Acumulus\Invoice\Item item line} of a {@see \Siel\Acumulus\Invoice\Source}.
 *
 * Product is used to provide unified access to information about a product or service.
 * Furthermore, by wrapping it in a single, library defined, object type, web shop
 * products can be passed around in a strongly typed way.
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

    public function __construct(int|string|object|array|null $productOrId, ?Item $item, Container $container)
    {
        $this->item = $item;
        $this->initializeWrapper($productOrId, $container);
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

    /**
     * Returns the locally stored id this product has in Acumulus.
     *
     * @return int|null
     *   The locally stored id this product has in Acumulus, or null if not (yet) stored.
     */
    public function getAcumulusId(): ?int
    {
        return null;
    }

    /**
     * Sets the locally stored id this product has in Acumulus.
     *
     * @param int|null $acumulusId
     *   The id this product has in Acumulus, or null to remove it.
     */
    public function setAcumulusId(?int $acumulusId): void
    {
        throw new RuntimeException(sprintf('%s::%s(): Not implemented', get_class(), __FUNCTION__));
    }

    /**
     * Returns the value of the field that is used to match products in the shop with
     * products in Acumulus.
     *
     * @return int|string|null
     *   The value of the field that is used to match products in this web shop with
     *   products in Acumulus, which may be an empty value indicating the product cannot
     *   be matched to a product in Acumulus.
     *
     * @noinspection PhpUnused  Used in the FieldExpander via  a mapping in
     *   {@see \Siel\Acumulus\Config\Mappings::getShopIndependentDefaults()}.
     */
    public function getReferenceForAcumulusLookup(): int|string|null
    {
        return $this->expandField($this->getConfig()->get('productMatchShopField'));
    }

//    /**
//     * Returns the human-readable name of the product.
//     *
//     * @todo: make this method abstract when all shops have implemented it.
//     */
//    public function getName(): string
//    {
//        throw new RuntimeException(sprintf('%s::%s(): Not implemented', get_class(), __FUNCTION__));
//    }
//
//    /**
//     * Returns the id of the vat class of the product.
//     */
//    abstract public function getVatClassId(): int|string;
//
//    /**
//     * Returns the name of the vat class of the product.
//     */
//    abstract public function getVatClassName(): string;

    /**
     * Returns the {@see Product} on which the stock is managed for this product.
     *
     * This will typically be the product itself, but in case of variants that do not need
     * separate stock, it may be the parent product. Note: that this is probably a sign of
     * misconfiguration, as few are products with variants for which all variants ship the
     * same parent product (electricity cables with some predefined lengths as variants
     * and cut on order, but even there you will typically see prepackaged lengths of 10,
     * 25, 50 and 100m.)
     */
    public function getStockManagingProduct(): static
    {
        return $this;
    }

    /**
     * Expands a field specification in a local product context.
     *
     * @return int|string|null
     *   The expanded field expansion specification, which may be empty if the
     *   properties or methods referred to do not exist or are or return an empty value
     *   themselves.
     *
     *   The type of the return value is either:
     *   - If $fieldSpecification contains exactly 1 field specification: the (return)
     *     type of the property or method specified in $field (which should be an int or a
     *     string).
     *   - Otherwise: string.
     */
    protected function expandField(string $fieldSpecification): int|string|null
    {
        return $this->getFieldExpander()->expand($fieldSpecification, $this->getProductPropertySources());
    }

    /**
     * Returns a {@see PropertySources} object with the product as property source.
     */
    private function getProductPropertySources(): PropertySources
    {
        return $this->getContainer()->createPropertySources()->add('product', $this);
    }

    private function getFieldExpander(): FieldExpander
    {
        return $this->getContainer()->getFieldExpander();
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->getConfig();
    }
}
