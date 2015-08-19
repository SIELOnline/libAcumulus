<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Order;
use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a PrestaShop order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var \Order */
  protected $source;

  /**
   * Loads an Order source for the set id.
   */
  protected function setSourceOrder() {
    $this->source = new Order($this->id);
  }

  /**
   * Sets the id based on the loaded Order.
   */
  protected function setIdOrder() {
    $this->id = $this->source->id;
  }

}
