<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;

/**
 * Product is a wrapper/adapter around a WHMCS specific product (appearing on an Item).
 *
 * @property array $shopObject
 * @method array getShopObject()
 */
class Product extends BaseProduct
{

    // @todo: generalize to AcumulusProductIdField and move to BaseProduct?
    public static string $acumulusProductIdField = '_acumulus_product_id';

    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WHMCS');
    }

    protected function setId(): void
    {
        /** @noinspection PhpUndefinedMethodInspection false positive */
        $this->id = $this->getShopObject()['id'];
    }

    public function getReference(): string
    {
        $reference = $this->getShopObject()['slug'];
        if (empty($reference)) {
            $reference = (string) $this->getId();
        }
        return $reference;
    }

    public function getName(): string
    {
        return $this->getShopObject()['name'];
    }

    public function getAcumulusId(): ?int
    {
        /** @noinspection PhpUndefinedMethodInspection false positive */
        $metaValue = $this->getShopObject()->get_meta(static::$acumulusProductIdField);

        return !empty($metaValue) ? (int) $metaValue : null;
    }

    public function setAcumulusId(?int $acumulusId): void
    {
        if ($acumulusId !== null) {
            /** @noinspection PhpUndefinedMethodInspection false positive */
            $this->getShopObject()->add_meta_data(static::$acumulusProductIdField, $acumulusId, true);
        } else {
            /** @noinspection PhpUndefinedMethodInspection false positive */
            $this->getShopObject()->delete_meta_data(static::$acumulusProductIdField);
        }
        /** @noinspection PhpUndefinedMethodInspection false positive */
        $this->getShopObject()->save_meta_data();
    }

    //    public function getVatClass(): string
    //    {
    //        return $this->shopObject->get_tax_class();
    //    }
}
