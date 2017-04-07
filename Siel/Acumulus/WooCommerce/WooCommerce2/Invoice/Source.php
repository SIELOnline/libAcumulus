<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\WooCommerce\Invoice\Source as BaseSource;

/**
 * Wraps a WooCommerce2 order in an invoice source object.
 *
 * This class only overrides methods that contain non BC compatible changes of
 * WooCommerce 3.
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
}
