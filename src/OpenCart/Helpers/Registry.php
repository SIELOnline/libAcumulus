<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

/**
 * Registry is a wrapper around the OpenCart registry instance which is not
 * directly accessible as the single instance is passed to each constructor in
 * the OpenCart classes.
 *
 * @property \Opencart\System\Engine\Config|\Config config
 * @property \Opencart\System\Library\DB|\DB db
 * @property \Opencart\System\Library\Document|\Document document
 * @property \Opencart\System\Engine\Event|\Event|\Light_Event event
 * @property \Opencart\System\Library\Language|\Language language
 * @property \Opencart\System\Engine\Loader|\Loader load
 * @property \Opencart\System\Library\Request|\Request request
 * @property \Opencart\System\Library\Response|\Response response
 * @property \Opencart\System\Library\Session|\Session session
 * @property \Opencart\System\Library\Url|\Url url
 */
abstract class Registry
{
    protected static Registry $instance;
    /**
     * @var \Opencart\System\Engine\Registry|\Registry
     */
    protected $registry;
    /**
     * @var \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order|\ModelCheckoutOrder|\ModelSaleOrder
     */
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
     * @param \Opencart\System\Engine\Registry|\Registry $registry
     *   The OpenCart Registry object.
     */
    public function __construct($registry)
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
     * Returns the full route for the given method and extension.
     *
     * In OpenCart, routes are used in the following places:
     * - Controller actions: via a URL.
     * - Events: when registering for one of the default 'before' or
     *   'after' events on controller and model methods.
     * - Events: to define the action (i.e. method) to be executed when
     *   registering for an event.
     * - Loader: to load a model, or view. This is handled by
     *   {@see getLoadRoute()}.
     * - Access: each controller route is also a value to determine access or
     *   modification rights.
     *
     * @param string $method
     *   The part of the route within the given $extension, may be empty for the
     *   default action index().
     * @param string $extension
     *   The extension that implements the $action, or empty for OpenCart core
     *   controller routes.
     * @param string $extensionType
     *   The type of extension: e.g. 'module' or 'payment'.
     *
     * @return string
     *   The full route for the given action (will not end with a /).
     */
    abstract public function getRoute(string $method, string $extension = 'acumulus', string $extensionType = 'module'): string;

    /**
     * Returns the full route to load the given object (view, language).
     *
     * @param string $object
     *   The view or language file to load.
     * @param string $extension
     *   The extension that contains the object, or empty for OpenCart core
     *   controller routes.
     * @param string $extensionType
     *   The type of extension: e.g. 'module' or 'payment'.
     *
     * @return string
     *   The full route for the given action (will not end with a /).
     */
    abstract public function getLoadRoute(string $object, string $extension = 'acumulus', string $extensionType = 'module'): string;

    /**
     * Returns the trigger for an Acumulus event.
     *
     * @param string $trigger
     *   The local part of the trigger.
     * @param string $moment
     *   Normally, one of 'before' or 'after'.
     *
     * @return string
     *   The full trigger to which other extensions can subscribe.
     */
    abstract public function getAcumulusTrigger(string $trigger, string $moment): string;

    /**
     * Returns the url to the given action.
     *
     * @param string $method
     *   The action, within the given $extension, to execute.
     * @param string $extension
     *   The extension that implements the $action, or empty for OpenCart core
     *   controller routes.
     * @param string $type
     *   The type of extension: e.g. 'module' or 'payment'. Ignored for core
     *   controller routes.
     *
     * @return string
     *   The url including the full route to the given action and the
     *   user_token.
     */
    public function getRouteUrl(string $method, string $extension = 'acumulus', string $type = 'module'): string
    {
        $token = 'user_token';
        $route = $this->getRoute($method, $extension, $type);
        return $this->url->link($route, $token . '=' . $this->session->data[$token], true);
    }

    /**
     * Returns a full URL to the given $file.
     *
     * This will typically be an image, css, or js file.
     *
     * @param string $file
     *   The sub-path of the file within the extension folder. Will typically
     *   start with 'view/' and in OC3 that means the 'view' folder under the
     *   'admin' folder.
     * @param string $extension
     *   The extension providing the file.
     *
     * @return string
     *   The full URL to the requested file, does not (need to) contain a
     *   user_token.
     */
    abstract public function getFileUrl(string $file = '', string $extension = 'acumulus'): string;

    /**
     * Returns a model based on the given name.
     *
     * @param string $modelName
     *   The model to get: 'name_space/[sub_name_space/]model'. This will load
     *   a model of the class with FQN
     *   \OpenCart\{application}\Model\NamSpace\SubNameSpace\Model, where
     *   {application} is one of 'Catalog' or 'Admin', depending on the request.
     *
     * @return \Opencart\System\Engine\Model
     *   Actually, a {@see \Opencart\System\Engine\Proxy} is returned that
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

    /**
     * Returns the order model that can be used to call:
     * - getOrder()
     * - getOrderProducts()
     * - getOrderOptions()
     * - getOrderTotals()
     *
     * As this model differs depending on whether we are in the Catalog or Admin
     * section, we cannot use getModel(), so this is a separate method.
     *
     * @return \Opencart\Admin\Model\Sale\Order|\Opencart\Catalog\Model\Checkout\Order|\ModelCheckoutOrder|\ModelSaleOrder
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *   Actually, this method returns a {@see \Opencart\System\Engine\Proxy}, not on of
     *   the Order types.
     */
    public function getOrderModel()
    {
        if (!isset($this->orderModel)) {
            $this->orderModel = $this->getModel($this->inAdmin() ? 'sale/order' : 'checkout/order');
        }
        return $this->orderModel;
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
     * indicates whether we are in Admin (true) or Catalog (false).
     */
    abstract protected function inAdmin(): bool;
}
