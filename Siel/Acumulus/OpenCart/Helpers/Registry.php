<?php
namespace Siel\Acumulus\OpenCart\Helpers;

/**
 * Class Registry
 *
 * @property \Config config
 * @property \Loader load
 * @property \DB\MySQLi db
 * @property \Event event
 * @property \Request request
 * @property \ModelAccountOrder model_account_order
 * @property \ModelCatalogProduct model_catalog_product
 * @property \ModelCheckoutOrder model_checkout_order
 * @property \ModelLocalisationOrderStatus model_localisation_order_status
 * @property \ModelSettingSetting model_setting_setting
 */
class Registry {
  /** @var Registry */
  protected static $instance;

  /** @var \Registry */
  protected $registry;

  /**
   * Sets the OC Registry.
   *
   * @param \Registry $registry
   */
  public static function setRegistry($registry) {
    static::$instance = new Registry($registry);
  }

  /**
   * Registry constructor.
   *
   * @param \Registry $registry
   */
  public function __construct(\Registry $registry) {
    $this->registry = $registry;
  }


  /**
   * Returns the Registry instance.
   *
   * @return Registry
   */
  public static function getInstance() {
    return static::$instance;
  }

  public function __get($key) {
    return $this->registry->get($key);
  }

  public function __set($key, $value) {
    $this->registry->set($key, $value);
  }
}
