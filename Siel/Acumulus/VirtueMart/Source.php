<?php
namespace Siel\Acumulus\VirtueMart;

use Siel\Acumulus\Invoice\Source as BaseSource;
use VmConfig;
use VmModel;

/**
 * Wraps a VirtueMart order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var int */
  protected $id;

  /** @var array */
  protected $source;

  /**
   * {@inheritdoc}
   */
  protected function setSource() {
    /** @var \VirtueMartModelOrders $orders */
    $orders = VmModel::getModel('orders');
    $this->source = $orders->getOrder($this->id);
  }

  /**
   * {@inheritdoc}
   */
  protected function setId() {
    $this->id = $this->source['details']['BT']->virtuemart_order_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReference() {
    return $this->source['details']['BT']->order_number;
  }

}
