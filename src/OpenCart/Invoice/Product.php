<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;

/**
 * Product does foo.
 */
class Product extends \Siel\Acumulus\Invoice\Product
{
    /**
     * @inheritDoc
     */
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in OpenCart');
    }

    /**
     * @inheritDoc
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()['product_id'];
    }
}
