<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\WooCommerce\Invoice\Source as BaseSource;

/**
 * Wraps a WooCommerce2 order in an invoice source object.
 *
 * This class only overrides methods that contain non BC compatible changes made
 * in the parent class to make WooCommerce 3 work.
 */
class Source extends BaseSource
{
    /**
     * {@inheritdoc}
     */
    protected function setId()
    {
        $this->id = $this->source->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        // Method get_order_number() is used for when other plugins are
        // installed that add an order number that differs from the ID. Known
        // plugins that do so: woocommerce-sequential-order-numbers(-pro) and
        // wc-sequential-order-numbers.
        return $this->source->get_order_number();
    }

    /**
     * Returns the order (or credit memo) date.
     *
     * @return string
     *   The order (or credit memo) date: yyyy-mm-dd.
     */
    public function getDate() {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * @inheritDoc
     */
    public function getDateOrder()
    {
        return substr($this->getSource()->order_date, 0, strlen('2000-01-01'));
    }

    /**
     * @inheritDoc
     */
    public function getDateCreditNote()
    {
        return substr($this->getSource()->post->post_date, 0, strlen('2000-01-01'));
    }
}
