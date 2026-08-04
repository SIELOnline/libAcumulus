<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Product;

use RuntimeException;
use Siel\Acumulus\Product\Product as BaseProduct;

/**
 * Product is a wrapper/adapter around a WHMCS specific product (appearing on an Item).
 *
 * As we use Products for synchronising stock keeping, we do, for now, not implement this
 * in WHMCS (methods getAcumulusId() and setAcumulusId()).
 *
 * @property array $shopObject
 * @method array getShopObject()
 */
class Product extends BaseProduct
{

    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in WHMCS');
    }

    protected function setId(): void
    {
        $this->id = $this->getShopObject()['pid'];
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
}
