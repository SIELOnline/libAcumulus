<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopOrderClass;
use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a HikaShop order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var object */
  protected $source;

  /**
   * Loads an Order source for the set id.
   */
  protected function setSourceOrder() {
    /** @var hikashopOrderClass $class */
    $class = hikashop_get('class.order');
    $this->source = $class->get($this->id);
  }

  /**
   * Sets the id based on the loaded Order.
   */
  protected function setIdOrder() {
    $this->id = $this->source->order_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReference() {
    return $this->source->order_number;
  }

}
