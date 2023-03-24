<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 *   Most parent methods are more or less empty stubs or return a default when
 *   the child does not override it.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use VmModel;

use function in_array;

/**
 * Wraps a VirtueMart order in an invoice source object.
 *
 * @property array $order
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
        /** @var \VirtueMartModelOrders $orders */
        $orders = VmModel::getModel('orders');
        $this->shopSource = $orders->getOrder($this->getId());
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setIdOrder(): void
    {
        $this->id = $this->getSource()['details']['BT']->virtuemart_order_id;
    }

    public function getReference()
    {
        return $this->getSource()['details']['BT']->order_number;
    }

    public function getDate(): string
    {
        return date(Api::DateFormat_Iso, strtotime($this->getSource()['details']['BT']->created_on));
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   A single character indicating the order status.
     */
    public function getStatus(): string
    {
        return $this->getSource()['details']['BT']->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the 'virtuemart_paymentmethod_id'.
     */
    public function getPaymentMethod()
    {
        return $this->getSource()['details']['BT']->virtuemart_paymentmethod_id ?? parent::getPaymentMethod();
    }

    public function getPaymentStatus(): int
    {
        return in_array($this->getSource()['details']['BT']->order_status, $this->getPaidStatuses(), false)
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
    {
        $date = null;
        $previousStatus = '';
        foreach ($this->getSource()['history'] as $orderHistory) {
            if (in_array($orderHistory->order_status_code, $this->getPaidStatuses(), false)
                && !in_array($previousStatus, $this->getPaidStatuses(), false)
            ) {
                $date = $orderHistory->created_on;
            }
            $previousStatus = $orderHistory->order_status_code;
        }
        return $date ? date(Api::DateFormat_Iso, strtotime($date)) : $date;
    }

    /**
     * Returns a list of order statuses that indicate that the order has been
     * paid.
     *
     * @return array
     */
    protected function getPaidStatuses(): array
    {
        return ['C', 'S', 'R'];
    }

    public function getCountryCode(): string
    {
        if (!empty($this->getSource()['details']['BT']->virtuemart_country_id)) {
            /** @var \VirtueMartModelCountry $countryModel */
            $countryModel = VmModel::getModel('country');
            $country = $countryModel->getData($this->getSource()['details']['BT']->virtuemart_country_id);
            return $country->country_2_code;
        }
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * VirtueMart stores the internal currency id of the currency used by the
     * customer in the field 'user_currency_id', so look up the currency object
     * first then extract the ISO code for it.
     *
     * However, the amounts stored are in the shop's default currency, even if
     * another currency was presented to the customer, so we will not have to
     * convert the amounts and this meta info is thus purely informative.
     */
    public function getCurrencyMeta(): array
    {
        // Load the currency.
        /** @var \VirtueMartModelCurrency $currency_model */
        $currency_model = VmModel::getModel('currency');
        /** @var \TableCurrencies $currency */
        $currency = $currency_model->getCurrency($this->getSource()['details']['BT']->user_currency_id);
        return [
            Meta::Currency => $currency->currency_code_3,
            Meta::CurrencyRate => (float) $this->getSource()['details']['BT']->user_currency_rate,
            Meta::CurrencyDoConvert => false,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount as they may be needed by the Completor.
     */
    protected function getAvailableTotals(): array
    {
        return [
            Meta::InvoiceAmountInc => (float) $this->getSource()['details']['BT']->order_total,
            Meta::InvoiceVatAmount => (float) $this->getSource()['details']['BT']->order_billTaxAmount,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function setInvoice(): void
    {
        $orderModel = VmModel::getModel('orders');
        /** @var \TableInvoices $invoicesTable */
        $invoicesTable = $orderModel->getTable('invoices');
        if ($invoice = $invoicesTable->load($this->getSource()['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
            $this->invoice = $invoice->getProperties();
        }
    }

    /**
     * See {@see getInvoiceReference}
     *
     * @noinspection PhpUnused
     */
    public function getInvoiceReferenceOrder()
    {
        return !empty($this->invoice['invoice_number']) ? $this->invoice['invoice_number'] : null;
    }

    /**
     * See {@see getInvoiceDate}
     *
     * @noinspection PhpUnused
     */
    public function getInvoiceDateOrder()
    {
        return !empty($this->invoice['created_on']) ? date(Api::DateFormat_Iso, strtotime($this->invoice['created_on'])) : null;
    }
}
