<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Shop;

/**
 * ProductManager does foo.
 */
class ProductManager extends \Siel\Acumulus\Shop\ProductManager
{
    /** @noinspection PhpOverridingMethodVisibilityInspection  To be able to test this particular method */
    public function matchAcumulusProductInList(array $products, string $reference): ?array
    {
        return parent::matchAcumulusProductInList($products, $reference);
    }

}
