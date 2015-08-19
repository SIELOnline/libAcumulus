<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a WooCommerce order in an invoice source object.
 *
 * Since WC 2.2.0 multiple order types can be defined, @see
 * wc_register_order_type() and wc_get_order_types(). WooCommerce itself defines
 * 'shop_order' and 'shop_order_refund'. The base class for all these types of
 * orders is WC_Abstract_Order
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var \WC_Abstract_Order */
  protected $source;

  /**
   * Loads an Order source for the set id.
   */
  protected function setSourceOrder() {
    $this->source = WC()->order_factory->get_order($this->id);
  }

  /**
   * Sets the id based on the loaded Order.
   */
  protected function setIdOrder() {
    $this->id = $this->source->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReference() {
    return $this->source->get_order_number();
  }

}
