<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use function strlen;

/**
 * OC4 specific Registry code.
 */
class Registry extends \Siel\Acumulus\OpenCart\Helpers\Registry
{
    /**
     * Returns the part of the route that directs to this extension.
     *
     * @todo: somehow merge this with OC3 getLocation() to prevent possible
     *   polymorphic calls.
     */
    public function getExtensionRoute(string $extension = 'acumulus'): string
    {
        return "extension/$extension/module/$extension";
    }

    /**
     * Returns a link to a route from this extension.
     *
     * @param string $action
     *
     * @return string
     *   The link to the given route, including standard arguments.
     *
     *
     * @todo: somehow merge this with OC3 getLocation() to prevent possible
     *   polymorphic calls.
     */
    public function getExtensionPageUrl(string $action): string
    {
        if ($action !== '') {
            $action = '|' . $action;
        }
        $route = $this->getExtensionRoute() . $action;
        return $this->getLink($route);
    }

    /**
     * Returns the URL for a file of an extension.
     *
     * Typically, this file is an image, js, or css file.
     */
    public function getExtensionFileUrl(string $file = '', string $extension = 'acumulus'): string
    {
        return HTTP_CATALOG . substr(DIR_EXTENSION, strlen(DIR_OPENCART)) . $extension . '/' . strtolower(APPLICATION) . '/' . $file;
    }

    /**
     * Returns the order model that can be used to call:
     * - getOrder()
     * - getOrderProducts()
     * - getOrderOptions()
     * - getOrderTotals()
     *
     * @return \Opencart\Admin\Model\Sale\Order|\Opencart\Catalog\Model\Checkout\Order
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *   Actually, this method returns a @see \Opencart\System\Engine\Proxy}.
     */
    public function getOrderModel()
    {
        if (!isset($this->orderModel)) {
            $modelName = $this->config->get('application') === 'Catalog' ? 'Checkout/Order' : 'Sale/Order';
            $this->orderModel = $this->getModel($modelName);
        }
        return $this->orderModel;
    }
}
