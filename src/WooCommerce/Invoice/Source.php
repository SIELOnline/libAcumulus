<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use WC_Abstract_Order;
use WC_Coupon;

use function count;
use function sprintf;
use function strlen;

/**
 * Wraps a WooCommerce order in an invoice source object.
 *
 * Since WC 2.2.0 multiple order types can be defined, @see
 * wc_register_order_type() and wc_get_order_types(). WooCommerce itself defines
 * 'shop_order' and 'shop_order_refund'. The base class for all these types of
 * orders is WC_Abstract_Order
 *
 * @method WC_Abstract_Order getShopObject()
 */
class Source extends BaseSource
{
    /**
     * Loads an Order or refund source for the set id.
     *
     * @throws  \RuntimeException
     *   If $idOrSource is empty or not a valid source.
     */
    protected function setShopObject(): void
    {
        $order = wc_get_order($this->getId());
        if (!$order instanceof WC_Abstract_Order) {
            throw new RuntimeException(sprintf('Not a valid source id (%s %d)', $this->type, $this->id));
        }
        $this->shopObject = $order;
    }

    /**
     * Sets the id based on the loaded Order or Order refund.
     *
     * @throws \RuntimeException
     *   If $idOrSource is empty or not a valid source.
     */
    protected function setId(): void
    {
        if (!$this->shopObject instanceof WC_Abstract_Order) {
            $type = get_debug_type($this->shopObject);
            throw new RuntimeException("'$type' is not a WC_Abstract_Order");
        }
        /** @noinspection PhpUndefinedMethodInspection */
        $this->id = $this->getShopObject()->get_id();
    }

    /**
     * Returns the user facing reference for the web shop's invoice source.
     *
     * Method get_order_number() is used for when other plugins are installed that add an
     * order number that differs from the ID. Known plugins that do so:
     * - woocommerce-sequential-order-numbers(-pro)
     * - wc-sequential-order-numbers
     * - custom-order-numbers-for-woocommerce(-pro)
     *
     * @return string|int
     *   The user facing id for the web shop's invoice source. This is not
     *   necessarily the internal id.
     */
    public function getReference(): string|int
    {
        if ($this->getType() === Source::Order) {
            /** @var \WC_Order $order */
            $order = $this->shopObject;
            return $order->get_order_number();
        }
        return parent::getReference();
    }

    /**
     * @inheritDoc
     */
    public function getDate(): string
    {
        // get_date_created() returns a WC_DateTime which has a _toString() method.
        return substr((string) $this->getShopObject()->get_date_created(), 0, strlen('2000-01-01'));
    }

    /**
     * @inheritDoc
     *
     * @return string|null
     *   The slug of the status (e.g. wc-completed).
     */
    public function getStatus(): string|null
    {
        /** @noinspection PhpUndefinedMethodInspection false positive */
        return $this->getShopObject()->get_status();
    }

    /**
     * {@inheritdoc}
     *
     * @return ?string
     *   This override returns the slug/id of a WC_Payment_Gateway.
     */
    public function getPaymentMethod(): ?string
    {
        // Payment method is not stored for credit notes, so it is expected to
        // be the same as for its order.
        /** @var \WC_Order $order */
        $order = $this->getOrder()->getShopObject();
        return $order->get_payment_method();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     *
     * @noinspection PhpUnused : called via getPaymentStatus().
     */
    protected function getPaymentStatusOrder(): int
    {
        /** @var \WC_Order $order */
        $order = $this->getShopObject();
        return $order->is_paid() ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
    }

    /**
     * Returns whether the order refund has been paid or not.
     *
     * For now, we assume that a refund is paid back on creation.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     *
     * @noinspection PhpUnused Called via callTypeSpecificMethod().
     */
    protected function getPaymentStatusCreditNote(): int
    {
        return Api::PaymentStatus_Paid;
    }

    /**
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     *
     * @noinspection PhpUnused : called via getPaymentDate().
     */
    protected function getPaymentDateOrder(): string
    {
        // get_date_paid() returns a WC_DateTime which has a _toString() method.
        /** @noinspection PhpUndefinedMethodInspection false positive */
        return substr((string) $this->getShopObject()->get_date_paid(), 0, strlen('2000-01-01'));
    }

    /**
     * Returns the payment date of the order refund.
     * We take the last modified date as pay date.
     *
     * @return string
     *   The payment date of the order refund (yyyy-mm-dd).
     *
     * @noinspection PhpUnused : called via getPaymentDate().
     */
    protected function getPaymentDateCreditNote(): string
    {
        // get_date_modified() returns a WC_DateTime which has a _toString() method.
        return substr((string) $this->getShopObject()->get_date_modified(), 0, strlen('2000-01-01'));
    }

    public function getCountryCode(): string
    {
        // Billing information is not stored for credit notes, so it is expected
        // to be the same as for its order.
        /** @var \WC_Order $order */
        $order = $this->getOrder()->getShopObject();
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

    public function getCurrency(): Currency
    {
        /** @noinspection PhpUndefinedMethodInspection false positive */
        return new Currency($this->getShopObject()->get_currency());
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     *
     * @noinspection PhpCastIsUnnecessaryInspection
     *   WooCommerce is not so strict when it comes to documenting its "@return"
     *   types. So many return values advertised as float, will be strings
     *   representing a float.
     */
    public function getTotals(): Totals
    {
        return new Totals((float) $this->getShopObject()->get_total(), (float) $this->getShopObject()->get_total_tax());
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    protected function getShopOrderOrId(): int
    {
        /** @var \WC_Order_Refund $refund */
        $refund = $this->shopObject;
        /** @noinspection PhpCastIsUnnecessaryInspection numeric string will be returned */
        return (int) $refund->get_parent_id();
    }

    /**
     * {@inheritdoc}
     *
     * @return \WC_Order_Refund[]
     */
    protected function getShopCreditNotesOrIds(): array
    {
        /** @var \WC_Order $order */
        $order = $this->shopObject;
        return $order->get_refunds();
    }

    /**
     * This WooCommerce override wraps {@see \WC_Order_Item_Product}s in Items,
     * ignoring empty lines, that is, lines with 0 quantity and total ("comment" lines?).
     */
    protected function createItems(): array
    {
        $result = [];

        /** @var \WC_Order_Item_Product[] $items */
        $items = $this->getShopObject()->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            // Only add when this is not an empty line.
            if (!Number::isZero((float) $item->get_quantity()) || !Number::isZero((float) $item->get_total())) {
                $result[] = $this->getContainer()->createItem($item, $this);
            }
        }

        return $result;
    }

    /**
     * @return \WC_Order_Item_Shipping[]
     */
    public function getShippingLineInfos(): array
    {
        return $this->getShopObject()->get_shipping_methods();
    }

    /**
     * WooCommerce has general fee lines, so we override this method to add all fees at
     * once. As the type is unknown to us, it might include payment fees().
     *
     * @return \WC_Order_Item_Fee[]
     */
    public function getOtherLineInfos(): array
    {
        return $this->getShopObject()->get_fees();
    }


    /**
     * {@inheritdoc}
     *
     * In WooCommerce, discount amounts are distributed over the applicable item lines, so
     * we do not have to add discount lines. However, we still do add them for
     * completeness, but they will get a unit price of 0.
     *
     * For refunds without any articles (probably just a manual refund) we don't need to
     * know what discounts were applied on the original order. So we do not add lines for
     * them.
     */
    public function getDiscountLineInfos(): array
    {
        $result = [];

        if ($this->getType() !== Source::CreditNote || count($this->getItems()) > 0) {
            // Add a line for all coupons applied. Coupons are only stored on the order,
            // not on refunds, so use the order.
            /** @var \WC_Order $order */
            $order = $this->getOrder()->getShopObject();
            foreach ($order->get_coupon_codes() as $couponCode) {
                $result[] = new WC_Coupon($couponCode);
            }
        }
        return $result;
    }
}
