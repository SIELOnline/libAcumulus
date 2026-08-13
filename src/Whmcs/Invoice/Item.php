<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;

/**
 * Item is the WHMCS specific class to wrap a Source item.
 *
 * @property array $shopObject
 * @method array getShopObject()
 * @method \Siel\Acumulus\Whmcs\Product\Product|null getProduct()
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WHMCS');
    }

    protected function setId(): void
    {
        $this->id = $this->shopObject['id'];
    }

    /**
     * This WHMCS override returns null as we do not have product information or an id in
     * an invoice item.
     */
    protected function createProduct(): ?Product
    {
        return null;
    }
}
