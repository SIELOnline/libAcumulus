<?php
/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use function strlen;

/**
 * OC3 specific code for Registry.
 */
class Registry extends \Siel\Acumulus\OpenCart\Helpers\Registry
{
    /**
     * Returns the location of the extension's files.
     *
     * @return string
     *   The location of the extension's files.
     *
     * @todo: somehow merge this with OC4 getExtensionRoute() to prevent
     *   possible polymorphic calls.
     */
    public function getLocation(): string
    {
        return 'extension/module/acumulus';
    }

    /**
     * Returns the order model that can be used to call:
     * - getOrder()
     * - getOrderProducts()
     * - getOrderOptions()
     * - getOrderTotals()
     *
     * @return \ModelCheckoutOrder|\ModelSaleOrder
     */
    public function getOrderModel()
    {
        if (!isset($this->orderModel)) {
            if (strrpos(DIR_APPLICATION, '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
                // We are in the catalog section, use the checkout/order model.
                $modelName = 'checkout/order';
            } else {
                // We are in the admin section, use the sale/order model.
                $modelName = 'sale/order';
            }
            $this->orderModel = $this->getModel($modelName);
        }
        return $this->orderModel;
    }
}
