<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\WooCommerce\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Source as GrandParentSource;

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
    public function getDate()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * @inheritDoc
     */
    public function getDateOrder()
    {
        return substr($this->source->order_date, 0, strlen('2000-01-01'));
    }

    /**
     * @inheritDoc
     */
    public function getDateCreditNote()
    {
        return substr($this->source->post->post_date, 0, strlen('2000-01-01'));
    }

    public function getPaymentMethod()
    {
        if (isset($this->source->payment_method)) {
            return $this->source->payment_method;
        }
        return GrandParentSource::getPaymentMethod();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusOrder()
    {
        return $this->source->needs_payment() ? Api::PaymentStatus_Due : Api::PaymentStatus_Paid;
    }

    /**
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     */
    protected function getPaymentDateOrder()
    {
        /** @noinspection PhpUndefinedFieldInspection */
        return substr($this->source->paid_date, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the payment date of the order refund.
     *
     * We take the last modified date as pay date.
     *
     * @return string
     *   The payment date of the order refund (yyyy-mm-dd).
     */
    protected function getPaymentDateCreditNote()
    {
        return substr($this->source->post->post_modified, 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        return isset($this->source->billing_country) ? $this->source->billing_country : '';
    }

    /**
     * {@inheritdoc}
     *
     * @return \WC_Order|int
     */
    protected function getShopOrderOrId()
    {
        /** @var \WC_Order_Refund $refund */
        $refund = $this->source;
        return $refund->post->post_parent;
    }
}
