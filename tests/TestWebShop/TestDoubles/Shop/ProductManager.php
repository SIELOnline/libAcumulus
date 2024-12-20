<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\TestDoubles\Shop;

use Siel\Acumulus\Tests\Unit\ApiClient\ApiRequestResponseExamples;

/**
 * This test ProductManager returns a fixed list of products as defined imn the
 * {@see ApiRequestResponseExamples}
 */
class ProductManager extends \Siel\Acumulus\Shop\ProductManager
{
    public function getAcumulusProducts(string $filter): array
    {
        return ApiRequestResponseExamples::getInstance()->getMainResponse('products');
    }

}
