<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;

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
    /** @var \WC_Order|\WC_Order_Refund */
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
        $this->id = $this->source->get_id();
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        // Method get_order_number() is used for when other plugins are
        // installed that add an order number that differs from the ID. Known
        // plugins that do so: woocommerce-sequential-order-numbers(-pro),
        // wc-sequential-order-numbers, and custom-order-numbers-for-woocommerce(-pro).
        if ($this->getType() === Source::Order) {
            /** @var \WC_Order $order */
            $order = $this->source;
            return $order->get_order_number();
        }
        return parent::getReference();
    }

    /**
     * @inheritDoc
     */
    public function getDate()
    {
        return substr($this->source->get_date_created(), 0, strlen('2000-01-01'));
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return $this->source->get_status();
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the id of a WC_Payment_Gateway.
     */
    public function getPaymentMethod()
    {
        // Payment method is not stored for credit notes, so it is expected to
        // be the same as for its order.
        /** @var \WC_Order $order */
        $order = $this->getOrder()->source;
        return $order->get_payment_method();
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
        return $this->source->is_paid() ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
    }

    /**
     * Returns whether the order refund has been paid or not.
     *
     * For now we assume that a refund is paid back on creation.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusCreditNote()
    {
        return Api::PaymentStatus_Paid;
    }

    /**
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     */
    protected function getPaymentDateOrder()
    {
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->source->get_date_paid();
        return substr($string, 0, strlen('2000-01-01'));
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
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->source->get_date_modified();
        return substr($string, 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        // Billing information is not stored for credit notes, so it is expected
        // to be the same as for its order.
        /** @var \WC_Order $order */
        $order = $this->getOrder()->source;
        $tax_based_on = get_option('woocommerce_tax_based_on');
        $result = '';
        if ($tax_based_on === 'shipping') {
            $result = $order->get_shipping_country();
        }
        if (empty($result)) {
            $result = $order->get_billing_country();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * WooCommerce does not support multiple currencies, so the amounts are
     * always in the shop's default currency. Even if another plugin is used to
     * present another currency to the customer, the amounts stored will
     * (probably) still be in euro's. So, we will not have to convert the
     * amounts and this meta info is thus purely informative.
     */
    public function getCurrency()
    {
        $result = array(
            Meta::Currency => 'EUR',
            Meta::CurrencyRate => 1.0,
            Meta::CurrencyDoConvert => false,
        );
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getAvailableTotals()
    {
        return array(
            Meta::InvoiceAmountInc => $this->source->get_total(),
            Meta::InvoiceVatAmount => $this->source->get_total_tax(),
        );
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
        return $refund->get_parent_id();
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopCreditNotesOrIds()
    {
        /** @var \WC_Order $order */
        $order = $this->source;
        return $order->get_refunds();
    }
}
