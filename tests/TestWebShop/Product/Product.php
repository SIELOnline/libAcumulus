<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Product;

use Siel\Acumulus\Product\Product as BaseProduct;
use stdClass;

/**
 * Product is the TestWebShop specific class to wrap a product appearing on an item line.
 */
class Product extends BaseProduct
{
    public const SKU = 'TESTSKU-ZWA';

    protected function setShopObject(): void
    {
        $this->shopObject = new stdClass();
        $this->shopObject->id = $this->id;
        $this->shopObject->name = 'Trui zwart';
        $this->shopObject->Sku = self::SKU;
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->id;
    }

    public function getAcumulusId(): ?int
    {
        return $this->shopObject->acumulusId ?? null;
    }

    public function setAcumulusId(?int $acumulusId): void
    {
        $this->shopObject->acumulusId = $acumulusId;
    }
}
