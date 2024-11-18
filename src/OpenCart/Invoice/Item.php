<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Invoice\Product;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Item is a wrapper/adapter around OpenCart specific order product lines.
 *
 * @property array $shopObject
 * @method array getShopObject()
 * @method \Siel\Acumulus\OpenCart\Invoice\Product|null getProduct()
 */
abstract class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in OpenCart');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()['order_product_id'];
    }

    protected function createProduct(): ?Product
    {
        // $product can be empty if the product has been deleted.
        /** @var \Opencart\Admin\Model\Catalog\Product|\ModelCatalogProduct $model */
        $model = $this->getRegistry()->getModel('catalog/product');
        $product = $model->getProduct($this->getShopObject()['product_id']);
        return !empty($product) ? $this->getContainer()->createProduct($product, $this) : null;
    }

    /**
     * Returns a list of order_option records.
     */
    abstract public function getOrderProductOptions(): array;

    /**
     * Returns an order model
     *
     * @return \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order|\ModelCheckoutOrder|\ModelSaleOrder
     *
     * noinspection ReturnTypeCanBeDeclaredInspection
     * @noinspection PhpReturnDocTypeMismatchInspection Actually, this method returns a
     *    {@see \Opencart\System\Engine\Proxy}, not the order model.
     */
    protected function getOrderModel()
    {
        return $this->getRegistry()->getOrderModel();
    }

    /**
     * Returns the product model
     *
     * @return \Opencart\Admin\Model\Catalog\Product|\ModelCatalogProduct
     *
     * noinspection ReturnTypeCanBeDeclaredInspection
     * @noinspection PhpReturnDocTypeMismatchInspection Actually, this method returns a
     *    {@see \Opencart\System\Engine\Proxy}, not the product model.
     */
    protected function getProductModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getRegistry()->getModel('catalog/product');
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
