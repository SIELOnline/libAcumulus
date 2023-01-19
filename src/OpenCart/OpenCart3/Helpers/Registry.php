<?php
/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use function strlen;

/**
 * Registry is a wrapper around the registry instance which is not directly
 * accessible as the single instance is passed to each constructor in the
 * OpenCart classes.
 *
 * @property \Config config
 * @property \DB db
 * @property \Document document
 * @property \Event|\Light_Event event
 * @property \Language language
 * @property \Loader load
 * @property \Request request
 * @property \Response response
 * @property \Session session
 * @property \Url url
 */
class Registry
{
    protected static Registry $instance;
    protected \Registry $registry;
    /** @var \ModelCheckoutOrder|\ModelSaleOrder */
    protected $orderModel;

    /**
     * Sets the OC Registry.
     */
    protected static function setInstance(Registry $registry): void
    {
        static::$instance = $registry;
    }

    /**
     * Returns the Registry instance.
     */
    public static function getInstance(): Registry
    {
        return static::$instance;
    }

    /**
     * Registry constructor.
     *
     * @param \Registry $registry
     *   The OpenCart Registry object.
     */
    public function __construct(\Registry $registry)
    {
        $this->registry = $registry;
        static::setInstance($this);
    }

    /**
     * Magic method __get must be declared public.
     */
    public function __get(string $key)
    {
        return $this->registry->get($key);
    }

    /**
     * Magic method __set must be declared public.
     *
     * @noinspection MagicMethodsValidityInspection
     */
    public function __set(string $key, $value)
    {
        $this->registry->set($key, $value);
    }

    /**
     * Returns the location of the extension's files.
     *
     * @return string
     *   The location of the extension's files.
     */
    public function getLocation(): string
    {
        return 'extension/module/acumulus';
    }

    /**
     * Returns the order.
     *
     * @return array|false
     *
     * @throws \Exception
     */
    public function getOrder(int $orderId)
    {
        return $this->getOrderModel()->getOrder($orderId);
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

    /**
     * Returns the model that can be used to add or remove events.
     *
     * @param string $modelName
     *  The model to get: should be of the form 'namespace/[subnamespace/]model'.
     *
     * @return \Model
     *  Actually a {@see Proxy} is returned that proxies a
     *  \ModelGroup1Group2Model class... (aka Duck typing)
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection  Actually a {@see Proxy}
     *   is returned that proxies a \ModelGroup1Group2Model class.
     * @noinspection PhpDocMissingThrowsInspection  Will throw an \Exception
     *   when the model class is not found, but that should be considered a
     *   development error.
     */
    public function getModel(string $modelName)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->load->model($modelName);
        $modelProperty = str_replace('/', '_', "model_$modelName");
        return $this->registry->get($modelProperty);
    }

    /**
     * Returns a link to the given route.
     *
     * @param string $route
     *
     * @return string
     *   The link to the given route, including standard arguments.
     */
    public function getLink(string $route): string
    {
        $token = 'user_token';
        return $this->url->link($route, $token . '=' . $this->session->data[$token], true);
    }
}
