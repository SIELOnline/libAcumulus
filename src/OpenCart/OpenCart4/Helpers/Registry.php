<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use function strlen;

/**
 * Registry is a wrapper around the OpenCart registry instance which is not
 * directly accessible as the single instance is passed to each constructor in
 * the OpenCart classes.
 *
 * @property \Opencart\System\Engine\Config config
 * @property \Opencart\System\Library\DB db
 * @property \Opencart\System\Library\Document document
 * @property \Opencart\System\Engine\Event event
 * @property \Opencart\System\Library\Language language
 * @property \Opencart\System\Engine\Loader load
 * @property \Opencart\System\Library\Request request
 * @property \Opencart\System\Library\Response response
 * @property \Opencart\System\Library\Session session
 * @property \Opencart\System\Library\Url url
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class Registry
{
    protected static Registry $instance;
    protected \Opencart\System\Engine\Registry $registry;
    // was: \ModelCheckoutOrder|\ModelSaleOrder
    /** @var \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order */
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
     * @param \Opencart\System\Engine\Registry $registry
     *   The OpenCart Registry object.
     */
    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        $this->registry = $registry;
        static::setInstance($this);
    }

    /**
     * Magic method __get must be declared public.
     *
     * @refactor should we make explicit getters for all uses of __get
     */
    public function __get(string $key)
    {
        return $this->registry->get($key);
    }

    /**
     * Magic method __set must be declared public.
     *
     * @refactor should we make explicit setters for all uses of __set
     *
     * @noinspection MagicMethodsValidityInspection
     */
    public function __set(string $key, $value)
    {
        $this->registry->set($key, $value);
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

    /**
     * Returns the part of the route that directs to this extension.
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
     * @return \Opencart\Admin\Model\Sale\Order
     */
    public function getOrderModel()
    {
        if (!isset($this->orderModel)) {
            $modelName = $this->config->get('application') === 'Catalog' ? 'Checkout/Order' : 'Sale/Order';
            $this->orderModel = $this->getModel($modelName);
        }
        return $this->orderModel;
    }

    /**
     * Returns the model that can be used to add or remove events.
     *
     * @todo load->model($modelName) loads class
     *   \OpenCart\[application]\Model\Model\Name

     * @param string $modelName
     *   The model to get: 'name_space/[sub_name_space/]model'. This will load
     *   a model of the class with FQN
     *   \OpenCart\{application}\Model\NamSpace\SubNameSpace\Model, where
     *   {application} is one of 'Catalog' or 'Admin', depending on the request.
     *
     * @return \Opencart\System\Engine\Model
     *   Actually a {@see \Opencart\System\Engine\Proxy} is returned that
     *   proxies a ModelGroup1Group2Model class. This follows the duck typing
     *   principle, thus we cannot define a strong typed return type here
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
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
}
