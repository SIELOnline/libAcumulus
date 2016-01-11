<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Log;

/**
 * Defines an interface to access the shop specific's config store.
 */
abstract class ConfigStore implements ConfigStoreInterface {

  /** @var string */
  protected $shopName;

  /**
   * ConfigStore constructor.
   *
   * @param string $shopNamespace
   */
  public function __construct($shopNamespace) {
    $pos = strrpos($shopNamespace, '\\');
    $this->shopName = $pos !== FALSE ? substr($shopNamespace, $pos + 1) : $shopNamespace;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    if (!empty($values['password'])) {
      $values['password'] = 'REMOVED FOR SECURITY';
    }
    Log::getInstance()->notice('ConfigStore::save(): saving %s', serialize($values));
  }


}
