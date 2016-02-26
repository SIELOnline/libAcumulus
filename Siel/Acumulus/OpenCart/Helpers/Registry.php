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
 * @property \Event event
 * @property \Loader load
 * @property \Request request
 * @property \ModelAccountOrder model_account_order
 * @property \ModelCatalogProduct model_catalog_product
 * @property \ModelCheckoutOrder model_checkout_order
 * @property \ModelSaleOrder model_sale_order
 * @property \ModelLocalisationOrderStatus model_localisation_order_status
 * @property \ModelSettingSetting model_setting_setting
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
    public static function setRegistry(/** @noinspection PhpUnusedParameterInspection */ \Registry $registry) {
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
    public function __construct(\Registry $registry)
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

    public function getOrderModel()
    {
        if ($this->orderModel === null) {
            if (strrpos(DIR_APPLICATION, '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
                // We are in the catalog section, use the account/order model.
                $this->load->model('account/order');
                $this->orderModel = $this->model_account_order;
            } else {
                // We are in the admin section, use the sale/order model.
                Registry::getInstance()->load->model('sale/order');
                $this->orderModel = Registry::getInstance()->model_sale_order;
            }
        }
        return $this->orderModel;
    }
}
