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
     * @noinspection PhpUnused : called via setSource().
     */
    protected function setSourceOrder(): void
    {
        /** @var hikashopOrderClass $class */
        $class = hikashop_get('class.order');
        $this->source = $class->loadFullOrder($this->id, true, false);
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setIdOrder(): void
    {
        $this->id = $this->source->order_id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->source->order_number;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): string
    {
        return date(Api::DateFormat_Iso, $this->source->order_created);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->source->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the name of the payment module.
     */
    public function getPaymentMethod()
    {
        return $this->source->order_payment_id ?? parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus(): int
    {
        /** @var \hikashopConfigClass $config */
        $config = hikashop_config();
        $unpaidStatuses = explode(',', $config->get('order_unpaid_statuses', 'created'));
        return in_array($this->source->order_status, $unpaidStatuses, true)
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
        foreach ($this->source->history as $history) {
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
            foreach ($this->source->history as $history) {
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
        return !empty($this->source->billing_address->address_country_code_2) ? $this->source->billing_address->address_country_code_2 : '';
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
        if (!empty($this->source->order_currency_info)) {
            $currency = unserialize($this->source->order_currency_info, ['allowed_classes' => [stdClass::class]]);
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
        if (!empty($this->source->order_tax_info)) {
            foreach ($this->source->order_tax_info as $taxInfo) {
                if (!empty($taxInfo->tax_amount)) {
                    $vatAmount += $taxInfo->tax_amount;
                }
            }
        }
        return [
            Meta::InvoiceAmountInc => (float) $this->source->order_full_price,
            Meta::InvoiceVatAmount => $vatAmount,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReference()
    {
        return !empty($this->source->order_invoice_number) ? $this->source->order_invoice_number : parent::getInvoiceReference();
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDate(): ?string
    {
        return !empty($this->source->order_invoice_created) ? date(Api::DateFormat_Iso, $this->source->order_invoice_created) : parent::getInvoiceDate();
    }
}
