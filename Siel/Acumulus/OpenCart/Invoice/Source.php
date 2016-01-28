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
    if (strrpos(DIR_APPLICATION, '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
      // We are in the catalog section, use the order model of account.
      Registry::getInstance()->load->model('checkout/order');
      $orderModel = Registry::getInstance()->model_checkout_order;
    }
    else {
      // We are in the admin section, use the order model of sale.
      Registry::getInstance()->load->model('sale/order');
      $orderModel = Registry::getInstance()->model_sale_order;
    }
    $this->source = $orderModel->getOrder($this->id);
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

  /**
   * {@inheritdoc}
   *
   * @return int
   */
  public function getStatus() {
    return $this->source['order_status_id'];
  }

}
