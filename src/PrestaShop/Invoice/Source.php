<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Configuration;
use Context;
use Country;
use Currency;
use Db;
use Order;
use OrderSlip;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;

use function strlen;

/**
 * Wraps a PrestaShop order in an invoice source object.
 *
 * @method Order|OrderSlip getSource()
 */
class Source extends BaseSource
{
    /**
     * {@inheritdoc}
     *
     * @throws \PrestaShopException
     */
    protected function setSource(): void
    {
        if ($this->getType() === Source::Order) {
            $this->shopSource = new Order($this->getId());
        } else {
            $this->shopSource = new OrderSlip($this->getId());
            $this->addProperties();
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the order reference or order slip id.
     */
    public function getReference()
    {
        return $this->getType() === Source::Order
            ? $this->getSource()->reference
            : Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id) . sprintf('%06d', $this->getSource()->id);
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function setId(): void
    {
        $this->id = $this->getSource()->id;
        if ($this->getType() === Source::CreditNote) {
            $this->addProperties();
        }
    }

    public function getDate(): string
    {
        return substr($this->getSource()->date_add, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the status of this order.
     *
     * @noinspection PhpUnused
     *   Called via getStatus().
     */
    protected function getStatusOrder(): int
    {
        return (int) $this->getSource()->current_state;
    }

    /**
     * Returns the status of this credit note.
     *
     * @noinspection PhpUnused
     *   Called via getStatus().
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *   null !== void
     */
    protected function getStatusCreditNote()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the name of the payment module.
     */
    public function getPaymentMethod()
    {
        /** @var \Order $order */
        $order = $this->getShopOrder()->shopSource;
        return $order->module ?? parent::getPaymentMethod();
    }

    public function getPaymentStatus(): int
    {
        // Assumption: credit slips are always in a paid status.
        return ($this->getType() === Source::Order && $this->getSource()->hasBeenPaid()) || $this->getType() === Source::CreditNote
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
    {
        if ($this->getType() === Source::Order) {
            $paymentDate = null;
            /** @var \Order $order */
            $order = $this->getShopOrder()->shopSource;
            foreach ($order->getOrderPaymentCollection() as $payment) {
                /** @var \OrderPayment $payment */
                if ($payment->date_add && ($paymentDate === null || $payment->date_add > $paymentDate)) {
                    $paymentDate = $payment->date_add;
                }
            }
        } else {
            // Assumption: last modified date is date of actual reimbursement.
            $paymentDate = $this->getSource()->date_upd;
        }

        return $paymentDate ? substr($paymentDate, 0, strlen('2000-01-01')) : null;
    }

    public function getCountryCode(): string
    {
        $invoiceAddress = new Address($this->getShopOrder()->shopSource->id_address_invoice);
        return !empty($invoiceAddress->id_country) ? Country::getIsoById($invoiceAddress->id_country) : '';
    }

    /**
     * {@inheritdoc}
     *
     * PrestaShop stores the internal currency id, so look up the currency
     * object first then extract the ISO code for it.
     */
    public function getCurrency(): array
    {
        $currency = Currency::getCurrencyInstance($this->getShopOrder()->shopSource->id_currency);
        /** @noinspection PhpCastIsUnnecessaryInspection */
        return [
            Meta::Currency => $currency->iso_code,
            Meta::CurrencyRate => (float) $this->getSource()->conversion_rate,
            Meta::CurrencyDoConvert => true,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-amount.
     */
    protected function getAvailableTotals(): array
    {
        $sign = $this->getSign();
        if ($this->getType() === Source::Order) {
            $amount = $this->getSource()->getTotalProductsWithoutTaxes()
                      + $this->getSource()->total_shipping_tax_excl
                      + $this->getSource()->total_wrapping_tax_excl
                      - $this->getSource()->total_discounts_tax_excl;
            $amountInc = $this->getSource()->getTotalProductsWithTaxes()
                         + $this->getSource()->total_shipping_tax_incl
                         + $this->getSource()->total_wrapping_tax_incl
                         - $this->getSource()->total_discounts_tax_incl;
        } else {
            // On credit notes, the amount ex VAT will not have been corrected
            // for discounts that are subtracted from the refund. This will be
            // corrected later in getDiscountLinesCreditNote().
            $amount = $this->getSource()->total_products_tax_excl
                      + $this->getSource()->total_shipping_tax_excl;
            $amountInc = $this->getSource()->total_products_tax_incl
                         + $this->getSource()->total_shipping_tax_incl;
        }

        return [
            Meta::InvoiceAmountInc => $sign * $amountInc,
            Meta::InvoiceAmount => $sign * $amount,
        ];
    }

    /**
     * Returns the invoice reference for an order
     *
     * @noinspection PhpUnused
     *   Called via getInvoiceReference().
     */
    public function getInvoiceReferenceOrder(): ?string
    {
        return !empty($this->getSource()->invoice_number)
            ? Configuration::get('PS_INVOICE_PREFIX', (int) $this->getSource()->id_lang, null, $this->getSource()->id_shop) . sprintf('%06d', $this->getSource()->invoice_number)
            : null;
    }

    /**
     * Returns the invoice date for an order
     *
     * @noinspection PhpUnused
     *   Called via getInvoiceDate().
     */
    public function getInvoiceDateOrder(): ?string
    {
        return !empty($this->getSource()->invoice_number)
            ? substr($this->getSource()->invoice_date, 0, strlen('2000-01-01'))
            : null;
    }

    protected function getShopOrderOrId()
    {
        /** @var \OrderSlip $orderSlip */
        $orderSlip = $this->shopSource;
        return $orderSlip->id_order;
    }

    protected function getShopCreditNotesOrIds()
    {
        /** @var \Order $order */
        $order = $this->shopSource;
        return $order->getOrderSlipsCollection();
    }

    /**
     * PS before 1.7.5 (it may have been fixed earlier, but this method is not a
     * problem to execute anyway):
     * OrderSlip does store but not load the values total_products_tax_excl,
     * total_shipping_tax_excl, total_products_tax_incl, and
     * total_shipping_tax_incl. As we need them, we load them ourselves.
     * Remove in the far future.
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function addProperties(): void
    {
        if (version_compare(_PS_VERSION_, '1.7.5', '<')) {
            $row = Db::getInstance()->executeS(sprintf('SELECT * FROM `%s` WHERE `%s` = %u',
                _DB_PREFIX_ . OrderSlip::$definition['table'], OrderSlip::$definition['primary'], $this->getId()));
            // Get 1st (and only) result.
            $row = reset($row);
            foreach ($row as $key => $value) {
                /** @noinspection PhpVariableVariableInspection */
                $this->getSource()->$key ??= $value;
            }
        }
    }
}
