<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopOrderClass;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use stdClass;

use function in_array;

/**
 * Wraps a HikaShop order in an invoice source object.
 *
 * @property object $order
 */
class Source extends BaseSource
{
    /**
     * Loads an Order source for the set id.
     *
     * @noinspection PhpUnused  Called via setShopSource().
     */
    protected function setShopSourceOrder(): void
    {
        /** @var hikashopOrderClass $class */
        $class = hikashop_get('class.order');
        $this->shopSource = $class->loadFullOrder($this->id, true, false);
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setIdOrder(): void
    {
        $this->id = $this->shopSource->order_id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->shopSource->order_number;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): string
    {
        return date(Api::DateFormat_Iso, $this->shopSource->order_created);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->shopSource->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the name of the payment module.
     */
    public function getPaymentMethod()
    {
        return $this->shopSource->order_payment_id ?? parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus(): int
    {
        /** @var \hikashopConfigClass $config */
        $config = hikashop_config();
        $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
        return in_array($this->shopSource->order_status, $unpaidStatuses, true)
            ? Api::PaymentStatus_Due
            : Api::PaymentStatus_Paid;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate(): ?string
    {
        // Scan through the history and look for a non-empty
        // 'history_payment_id'. The order of this array is by 'history_created'
        //  DESC, we take the one that is the furthest away in time.
        $date = null;
        foreach ($this->shopSource->history as $history) {
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
            foreach ($this->shopSource->history as $history) {
                if (!empty($history->history_new_status)
                    && !in_array($history->history_new_status, $unpaidStatuses, true)
                ) {
                    $date = $history->history_created;
                }
            }
        }
        return $date ? date(Api::DateFormat_Iso, $date) : $date;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode(): string
    {
        return !empty($this->shopSource->billing_address->address_country_code_2) ? $this->shopSource->billing_address->address_country_code_2 : '';
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
    public function getCurrency(): array
    {
        $result = [];
        if (!empty($this->shopSource->order_currency_info)) {
            $currency = unserialize($this->shopSource->order_currency_info, ['allowed_classes' => [stdClass::class]]);
            $result = [
                Meta::Currency => $currency->currency_code,
                Meta::CurrencyRate => (float) $currency->currency_rate,
                Meta::CurrencyDoConvert => true,
            ];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values 'meta-invoice-amountinc' and
     * 'meta-invoice-vatamount'.
     */
    protected function getAvailableTotals(): array
    {
        // No order_tax_info => no tax (?) => vat amount = 0.
        $vatAmount = 0.0;
        if (!empty($this->shopSource->order_tax_info)) {
            foreach ($this->shopSource->order_tax_info as $taxInfo) {
                if (!empty($taxInfo->tax_amount)) {
                    $vatAmount += $taxInfo->tax_amount;
                }
            }
        }
        return [
            Meta::InvoiceAmountInc => (float) $this->shopSource->order_full_price,
            Meta::InvoiceVatAmount => $vatAmount,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReference()
    {
        return !empty($this->shopSource->order_invoice_number) ? $this->shopSource->order_invoice_number : parent::getInvoiceReference();
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDate(): ?string
    {
        return !empty($this->shopSource->order_invoice_created) ? date(Api::DateFormat_Iso, $this->shopSource->order_invoice_created) : parent::getInvoiceDate();
    }
}
