<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;
use WC_Product;

/**
 * Product is a wrapper/adapter around a WooCommerce specific product (appearing on an Item).
 *
 * @property WC_Product $shopObject
 * @method WC_Product getShopObject()
 */
class Product extends BaseProduct
{
    // @todo: generalize to AcumulusProductIdField and move to BaseProduct?
    public static string $keyAcumulusProductId = '_acumulus_product_id';

    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WooCommerce');
    }

    protected function setId(): void
    {
        $this->id = $this->getShopObject()->get_id();
    }
//
//    public function getReference(): string
//    {
//        return $this->shopObject->get_sku();
//    }
//
//    public function getName(): string
//    {
//        return $this->shopObject->get_name();
//    }

    public function getAcumulusId(): ?int
    {
        $metaValue = $this->getShopObject()->get_meta(static::$keyAcumulusProductId);
        return !empty($metaValue) ? (int) $metaValue : null;
    }

    public function setAcumulusId(?int $acumulusId): void
    {
        if ($acumulusId !== null) {
            $this->getShopObject()->add_meta_data(static::$keyAcumulusProductId, $acumulusId, true);
        } else {
            $this->getShopObject()->delete_meta_data(static::$keyAcumulusProductId);
        }
        $this->getShopObject()->save_meta_data();
    }

//    public function getVatClass(): string
//    {
//        return $this->shopObject->get_tax_class();
//    }
}
