<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopOrderClass;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use stdClass;

use function count;
use function in_array;

/**
 * Wraps a HikaShop order in an invoice source object.
 *
 * @method object getShopObject() See \hikashopOrderClass
 */
class Source extends BaseSource
{
    /**
     * Loads an Order source for the set id.
     *
     * @noinspection PhpUnused  Called via setSource().
     */
    protected function setShopObject(): void
    {
        /** @var hikashopOrderClass $class */
        $class = hikashop_get('class.order');
        $this->shopObject = $class->loadFullOrder($this->getId(), true, false);
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()->order_id;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   A combination of letters and digits.
     */
    public function getReference(): string
    {
        return $this->getShopObject()->order_number;
    }

    public function getDate(): string
    {
        return date(Api::DateFormat_Iso, $this->getShopObject()->order_created);
    }

    /**
     * {@inheritdoc}
     *
     * @return string|null
     *   The english name of the status.
     */
    public function getStatus(): string|null
    {
        return $this->getShopObject()->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * @return ?int
     *   The id of the payment module or null if not set (does not happen in our test
     *   orders)
     */
    public function getPaymentMethod(): ?int
    {
        return isset($this->getShopObject()->order_payment_id)
            ? (int) $this->getShopObject()->order_payment_id
            : parent::getPaymentMethod();
    }

    public function getPaymentStatus(): int
    {
        /** @var \hikashopConfigClass $config */
        $config = hikashop_config();
        $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
        return in_array($this->getShopObject()->order_status, $unpaidStatuses, true)
            ? Api::PaymentStatus_Due
            : Api::PaymentStatus_Paid;
    }

    public function getPaymentDate(): ?string
    {
        // Scan through the history and look for a non-empty
        // 'history_payment_id'. The order of this array is by 'history_created'
        //  DESC, we take the one that is the furthest away in time.
        $date = null;
        foreach ($this->getShopObject()->history as $history) {
            if (!empty($history->history_payment_id)) {
                $date = $history->history_created;
            }
        }
        if (!$date) {
            // Scan through the history and look for a non-unpaid order status.
            // We take the one that is the furthest away in time.
            /** @var \hikashopConfigClass $config */
            $config = hikashop_config();
            $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
            foreach ($this->getShopObject()->history as $history) {
                if (!empty($history->history_new_status)
                    && !in_array($history->history_new_status, $unpaidStatuses, true)
                ) {
                    $date = $history->history_created;
                }
            }
        }
        return $date ? date(Api::DateFormat_Iso, $date) : $date;
    }

    public function getCountryCode(): string
    {
        return !empty($this->getShopObject()->billing_address->address_country_code_2)
            ? $this->getShopObject()->billing_address->address_country_code_2
            : '';
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop stores the currency info in a serialized object in the field
     * order_currency_info, so {@see unserialize()} to get the info.
     *
     * If you do show but not publicise a currency, the currency info and
     * amounts are stored as if the order was placed in the default currency,
     * thus we can no longer find out so at this point.
     */
    public function getCurrency(): Currency
    {
        if (!empty($this->getShopObject()->order_currency_info)) {
            $currency = unserialize($this->getShopObject()->order_currency_info, ['allowed_classes' => [stdClass::class]]);
            $result = new Currency($currency->currency_code, (float) $currency->currency_rate, true);
        } else {
            $result = new Currency();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values 'meta-invoice-amountinc' and
     * 'meta-invoice-vatamount'.
     */
    public function getTotals(): Totals
    {
        // No order_tax_info => no tax (?) => vat amount = 0.
        $vatAmount = 0.0;
        if (!empty($this->getShopObject()->order_tax_info)) {
            foreach ($this->getShopObject()->order_tax_info as $taxInfo) {
                if (!empty($taxInfo->tax_amount)) {
                    $vatAmount += $taxInfo->tax_amount;
                }
            }
        }
        return new Totals((float) $this->getShopObject()->order_full_price, $vatAmount, null);
    }

    public function getVatBreakdown(): array
    {
        // No order_tax_info => no tax (?) => no vat breakdown
        $vatBreakdown = [];
        if (!empty($this->getShopObject()->order_tax_info)) {
            foreach ($this->getShopObject()->order_tax_info as $taxInfo) {
                if (!empty($taxInfo->tax_amount)) {
                    $vatBreakdown[$taxInfo->tax_namekey] = $taxInfo->tax_amount;
                }
            }
        }
        return $vatBreakdown;
    }

    /**
     * {@inheritdoc}
     *
     * @return ?string
     *   A combination of letters and digits, or null if not (yet) set.
     */
    public function getInvoiceReference(): ?string
    {
        return !empty($this->getShopObject()->order_invoice_number)
            ? $this->getShopObject()->order_invoice_number
            : parent::getInvoiceReference();
    }

    public function getInvoiceDate(): ?string
    {
        return !empty($this->getShopObject()->order_invoice_created)
            ? date(Api::DateFormat_Iso, (int) $this->getShopObject()->order_invoice_created)
            : parent::getInvoiceDate();
    }

    protected function createItems(): array
    {
        $result = [];
        foreach ($this->getShopObject()->products as $orderProduct) {
            $result[] = $this->getContainer()->createItem($this, $orderProduct);
        }
        return $result;
    }

    public function getShippingLineInfos(): array
    {
        $order = $this->getShopObject();
        if (Number::isZero($order->order_shipping_price)) {
            // Free (or no) shipping: do not add on a credit note.
            $shippingInfos[] = null;
        } elseif (empty($order->order_shipping_params)
            || count($order->order_shipping_params->prices) === 0
        ) {
            // If the property order_shipping_params is "empty" (no info to
            // extract from), we use the order_shipping_* properties at the
            // order level.
            $shippingInfos[Source::Order] = $order;
        } else {
            // For each shipment we are going to add 1 or more shipping lines.
            $shippingInfos = $order->order_shipping_params->prices;
        }
        return $shippingInfos;
    }

    public function getPaymentFeeLineInfos(): array
    {
        return !Number::isZero($this->getShopObject()->order_payment_price) ? [$this] : [];
    }

    public function getDiscountLineInfos(): array
    {
        return !Number::isZero($this->getShopObject()->order_discount_price) ? [$this] : [];
    }
}
