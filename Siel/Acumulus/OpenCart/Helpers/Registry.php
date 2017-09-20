<?php
namespace Siel\Acumulus\OpenCart\Helpers;

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
/**
 * Registry is a wrapper around the registry instance which is not directly
 * accessible as the single instance is passed to each constructor in the
 * OpenCart classes.
 *
 * @property \Config config
 * @property \DBMySQLi|\DB\MySQLi db
 * @property \Document document
 * @property \Event event
 * @property \Language language
 * @property \Loader load
 * @property \Request request
 * @property \Response response
 * @property \Session session
 * @property \Url url
 * @property \ModelAccountOrder model_account_order
 * @property \ModelCatalogProduct model_catalog_product
 * @property \ModelCheckoutOrder model_checkout_order
 * @property \ModelExtensionEvent model_extension_event
 * @property \ModelSaleOrder model_sale_order
 * @property \ModelLocalisationOrderStatus model_localisation_order_status
 * @property \ModelSettingSetting model_setting_setting
 * @property \ModelSettingExtension model_setting_extension
 * @property \ModelExtensionExtension model_extension_extension
 */
class Registry
{
    /** @var \Siel\Acumulus\OpenCart\Helpers\Registry */
    protected static $instance;

    /** @var \Registry */
    protected $registry;

    /** @noinspection PhpUndefinedClassInspection */
    /** @var \ModelAccountOrder|\ModelSaleOrder */
    protected $orderModel;

    /**
     * Sets the OC Registry.
     *
     * @param \Registry $registry
     */
    public static function setRegistry(\Registry $registry) {
        static::$instance = new Registry($registry);
    }

    /**
     * Returns the Registry instance.
     *
     * @return \Siel\Acumulus\OpenCart\Helpers\Registry
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Registry constructor.
     *
     * @param \Registry $registry
     */
    protected function __construct(\Registry $registry)
    {
        $this->registry = $registry;
        $this->orderModel = null;
    }

    public function __get($key)
    {
        return $this->registry->get($key);
    }

    public function __set($key, $value)
    {
        $this->registry->set($key, $value);
    }

    /**
     * Returns whether we are in main version 1.
     *
     * @return bool
     *   True if the main version is 1, false otherwise.
     *
     */
    public function isOc1()
    {
        return version_compare(VERSION, '2', '<');
    }

    /**
     * Returns whether we are in version 2.3+.
     *
     * @return bool
     *   True if the version is 2.3 or higher, false otherwise.
     *
     */
    public function isOc23()
    {
        return version_compare(VERSION, '2.3', '>=');
    }

    /**
     * Returns whether we are in version 3+.
     *
     * @return bool
     *   True if the version is 3 or higher, false otherwise.
     *
     */
    public function isOc3()
    {
        return version_compare(VERSION, '3', '>=');
    }

    /**
     * Returns the location of the extension's files.
     *
     * @return string
     *   The location of the extension's files.
     */
    public function getLocation()
    {
        return $this->isOc23() ? 'extension/module/acumulus' : 'module/acumulus';
    }

    /**
     * Returns the order.
     *
     * @param int $orderId
     *
     * @return array|false
     */
    public function getOrder($orderId)
    {
        if (strrpos(str_replace('\\', '/', DIR_APPLICATION), '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
            // We are in the catalog section, use the checkout/order model as
            // ModelAccountOrder::getOrder() also checks on user_id!
            if ($this->model_checkout_order === null) {
                $this->load->model('checkout/order');
            }
            return $this->model_checkout_order->getOrder($orderId);
        } else {
            // We are in the admin section, we can use the normal order model.
            return $this->getOrderModel()->getOrder($orderId);
        }
    }

    /**
     * Returns the order model that can be used to call:
     * - getOrderProducts()
     * - getOrderOptions()
     * - GetOrderTotals()
     * And in admin only:
     * - getOrder()
     *
     * In catalog we need another model to call getOrder(), so we have a
     * separate method getOrder() for that here.
     *
     * @return \ModelAccountOrder|\ModelSaleOrder
     */
    public function getOrderModel()
    {
        if ($this->orderModel === null) {
            if (strrpos(DIR_APPLICATION, '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
                // We are in the catalog section, use the account/order model.
                $this->load->model('account/order');
                $this->orderModel = $this->model_account_order;
            } else {
                // We are in the admin section, use the sale/order model.
                $this->load->model('sale/order');
                $this->orderModel = $this->model_sale_order;
            }
        }
        return $this->orderModel;
    }

    /**
     * Returns a link to the given route.
     *
     * @param string $route
     *
     * @return string
     *   The link to the given route, including standard arguments.
     */
    public function getLink($route)
    {
        // Differences between the OC versions.
        // - token in OC1 and 2, user_token in OC3.
        $token = $this->isOc3() ? 'user_token' : 'token';
        // - 3rd argument is $connection = 'SSL' in OC1, and is $secure = true
        //   in OC2 and 3.
        $secure = $this->isOc1() ? 'SSL' : true;
        return $this->url->link($route, $token . '=' . $this->session->data[$token], $secure);
    }
}
