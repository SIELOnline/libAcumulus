<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;
use VmModel;

/**
 * Wraps a VirtueMart order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var array */
  protected $source;

  /**
   * Loads an Order source for the set id.
   */
  protected function setSourceOrder() {
    /** @var \VirtueMartModelOrders $orders */
    $orders = VmModel::getModel('orders');
    $this->source = $orders->getOrder($this->id);
  }

  /**
   * Sets the id based on the loaded Order.
   */
  protected function setIdOrder() {
    $this->id = $this->source['details']['BT']->virtuemart_order_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReference() {
    return $this->source['details']['BT']->order_number;
  }

  /**
   * {@inheritDoc}
   *
   * @return string
   *   A single character indicating the order status.
   */
  public function getStatus() {
    return $this->source['details']['order_status'];
  }

}
