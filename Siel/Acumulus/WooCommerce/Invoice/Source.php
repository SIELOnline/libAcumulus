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
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \WC_Abstract_Order */
    protected $source;

    /**
     * Loads an Order or refund source for the set id.
     */
    protected function setSource()
    {
        $this->source = WC()->order_factory->get_order($this->id);
    }

    /**
     * Sets the id based on the loaded Order or Order refund.
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

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return $this->source->get_status();
    }

    /**
     * {@inheritdoc}
     */
    protected function getOriginalOrder()
    {
        return new Source(Source::Order, $this->source->post->post_parent);
    }
}
