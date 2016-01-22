<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Wraps an OpenCart 2 order in an invoice source object.
 */
class Source extends BaseSource {

  // More specifically typed properties.
  /** @var array */
  protected $source;

  /**
   * {@inheritdoc}
   */
  protected function setSource() {
    Registry::getInstance()->load->model('checkout/order');
    Registry::getInstance()->model_checkout_order->getOrder($this->id);
    throw new \RuntimeException('An OpenCart Source can only be created by passing in an order array.');
  }

  /**
   * More specifically typed override. uncomment when developing.
   * @return array
   */
  public function getSource() {
    return parent::getSource();
  }

  /**
   * Sets the id based on the loaded Order.
   */
  protected function setId() {
    $this->id = $this->source['order_id'];
  }

}
