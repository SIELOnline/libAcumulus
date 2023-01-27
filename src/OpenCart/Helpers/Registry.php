<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use function strlen;

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
    protected \Opencart\System\Engine\Registry $registry;
    // was: \ModelCheckoutOrder|\ModelSaleOrder
    /** @var \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order|\ModelCheckoutOrder|\ModelSaleOrder */
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
     * Returns the URL for a file of an extension.
     *
     * Typically, this file is an image, js, or css file.
     *
     * @todo: make abstract and implement in OC3? omove this one to OC4.
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
     * @return \Opencart\Admin\Model\Sale\Order|\Opencart\Catalog\Model\Checkout\Order|\ModelCheckoutOrder|\ModelSaleOrder
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *   Actually, this method returns a @see \Opencart\System\Engine\Proxy}.
     */
    abstract public function getOrderModel();

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
}
