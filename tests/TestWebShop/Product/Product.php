<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Product;

use Siel\Acumulus\Product\Product as BaseProduct;
use stdClass;

/**
 * Product is the TestWebShop specific class to wrap a product appearing on an item line.
 *
 * TEST-GRO used for 't-shirt groen'::ean (1833637)
 * TEST-ZWA used for 't-shirt zwart'::sku (1833639) and 'trui zwart'::sku (1833643)
 * TEST-GRI used for 'trui grijs'::sku (1833642)
 * TEST used for 't-shirt blauw'::ean (1833636) and 't-shirt rood'::sku (1833638)
 */
class Product extends BaseProduct
{
    private static array $references = [
        13 => ['t-shirt groen', 'TEST-GRO'], // sku: null; ean: 't-shirt groen'; free: 't-shirt groen'
        14 => ['t-shirt zwart', 'TEST-ZWA'], // sku: 2 results; ean: null; free: 2 results
        15 => ['trui grijs', 'TEST-GRI'], // sku: 'trui grijs'; ean: null; free: 'trui grijs'
        16 => ['t-shirt rood', 'TEST'],     // sku: 't-shirt rood'; ean: 't-shirt blauw'; free: 6 results
        17 => ['t-shirt rood', 'TEST', 1833636],  // sku: 't-shirt rood'; ean: 't-shirt blauw'; free: 6 results, acumulusId (1833636): t-shirt blauw
        18 => ['t-shirt rood', 'TEST', 1], // sku: 't-shirt rood'; ean: 't-shirt blauw'; free: 6 results, acumulusId (1): null
    ];

    protected function setShopObject(): void
    {
        $this->shopObject = new stdClass();
        $this->shopObject->id = $this->id;
        $this->shopObject->name = self::$references[$this->id][0] ?? 'trui groen';
        $this->shopObject->sku = self::$references[$this->id][1] ?? 'my-sku';
        $this->shopObject->ean = self::$references[$this->id][1] ?? 'my-ean';
        $this->shopObject->acumulusId = self::$references[$this->id][2] ?? null;
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject->id;
    }

    public function getReference(): string
    {
        return $this->shopObject->sku;
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
