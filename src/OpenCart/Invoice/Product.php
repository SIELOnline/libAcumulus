<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;

/**
 * Product does foo.
 */
class Product extends \Siel\Acumulus\Invoice\Product
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in OpenCart');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()['product_id'];
    }
}
