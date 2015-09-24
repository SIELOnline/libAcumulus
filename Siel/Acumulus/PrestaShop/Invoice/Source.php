<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Order;
use OrderSlip;
use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a PrestaShop order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var Order|OrderSLip */
  protected $source;

  /**
   * {@inheritdoc}
   */
  protected function setSource() {
    $this->source = $this->getType() === Source::Order ? new Order($this->id) : new OrderSlip($this->id);
  }

  /**
   * @todo: remove after development
   * @return Order|OrderSlip
   */
  public function getSource() {
    return parent::getSource();
  }

  /**
   * {@inheritdoc}
   *
   * This override returns the order reference or order slip id.
   */
  public function getReference() {
    return $this->getType() === Source::Order ? $this->source->reference : parent::getReference();
  }


  /**
   * Sets the id based on the loaded Order.
   */
  protected function setId() {
    $this->id = $this->source->id;
  }

}
