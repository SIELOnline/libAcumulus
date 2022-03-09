<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use VmModel;

/**
 * Wraps a VirtueMart order in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var array */
    protected $source;

    /**
     * Loads an Order source for the set id.
     */
    protected function setSourceOrder()
    {
        /** @var \VirtueMartModelOrders $orders */
        $orders = VmModel::getModel('orders');
        $this->source = $orders->getOrder($this->id);
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setIdOrder()
    {
        $this->id = $this->source['details']['BT']->virtuemart_order_id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->source['details']['BT']->order_number;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate()
    {
        return date(Api::DateFormat_Iso, strtotime($this->source['details']['BT']->created_on));
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   A single character indicating the order status.
     */
    public function getStatus(): string
    {
        return $this->source['details']['BT']->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the 'virtuemart_paymentmethod_id'.
     */
    public function getPaymentMethod()
    {
        if (isset($this->source['details']['BT']->virtuemart_paymentmethod_id)) {
            return $this->source['details']['BT']->virtuemart_paymentmethod_id;
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus(): ?int
    {
        return in_array($this->source['details']['BT']->order_status, $this->getPaidStatuses())
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate()
    {
        $date = null;
        $previousStatus = '';
        foreach ($this->source['history'] as $orderHistory) {
            if (in_array($orderHistory->order_status_code, $this->getPaidStatuses()) && !in_array($previousStatus, $this->getPaidStatuses())) {
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

    /**
     * {@inheritdoc}
     */
    public function getCountryCode(): string
    {
        if (!empty($this->source['details']['BT']->virtuemart_country_id)) {
            /** @var \VirtueMartModelCountry $countryModel */
            $countryModel = VmModel::getModel('country');
            $country = $countryModel->getData($this->source['details']['BT']->virtuemart_country_id);
            return $country->country_2_code;
        }
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * VirtueMart stores the currency info as a serialised object in the field
     * 'order_currency_info', so {@see unserialize()} to get the info.
     *
     * VirtueMart stores the internal currency id of the currency used by the
     * customer in the field 'user_currency_id', so look up the currency object
     * first then extract the ISO code for it.
     *
     * However, the amounts stored are in the shop's default currency, even if
     * another currency was presented to the customer, so we will not have to
     * convert the amounts and this meta info is thus purely informative.
     */
    public function getCurrency(): array
    {
        // Load the currency.
        /** @var \VirtueMartModelCurrency $currency_model */
        $currency_model = VmModel::getModel('currency');
        /** @var \TableCurrencies $currency */
        $currency = $currency_model->getCurrency($this->source['details']['BT']->user_currency_id);
        return array (
            Meta::Currency => $currency->currency_code_3,
            Meta::CurrencyRate => (float) $this->source['details']['BT']->user_currency_rate,
            Meta::CurrencyDoConvert => false,
        );
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
            Meta::InvoiceAmountInc => $this->source['details']['BT']->order_total,
            Meta::InvoiceVatAmount => $this->source['details']['BT']->order_billTaxAmount,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function setInvoice()
    {
        $orderModel = VmModel::getModel('orders');
        /** @var \TableInvoices $invoicesTable */
        $invoicesTable = $orderModel->getTable('invoices');
        if ($invoice = $invoicesTable->load($this->source['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
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
