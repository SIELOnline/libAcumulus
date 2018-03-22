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
        $this->source = $orders->getMyOrderDetails($this->id);
    }

    /**
     * Sets the id based on the loaded Order.
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
        return date(API::DateFormat_Iso, strtotime($this->source['details']['BT']->created_on));
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   A single character indicating the order status.
     */
    public function getStatus()
    {
        return $this->source['details']['BT']->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the virtuemart_paymentmethod_id.
     */
    public function getPaymentMethod()
    {
        // @todo: test this: correct sub-array?
        if (isset($this->source['details']['BT']->virtuemart_paymentmethod_id)) {
            return $this->source['details']['BT']->virtuemart_paymentmethod_id;
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentState()
    {
        return in_array($this->source['details']['BT']->order_status, $this->getPaidStates())
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
            if (in_array($orderHistory->order_status_code, $this->getPaidStates()) && !in_array($previousStatus, $this->getPaidStates())) {
                $date = $orderHistory->created_on;
            }
            $previousStatus = $orderHistory->order_status_code;
        }
        return $date ? date(API::DateFormat_Iso, strtotime($date)) : $date;
    }

    /**
     * Returns a list of order states that indicate that the order has been
     * paid.
     *
     * @return array
     */
    protected function getPaidStates()
    {
        return array('C', 'S', 'R');
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
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
     * VirtueMart stores the currency info in the fields a serialzied object in the field
     * order_currency_info, so unserialize to get the info.
     *
     * VirtueMart stores the internal currency id of the currency used by the
     * customer in the field user_currency_id, so look up the currency object
     * first then extract the ISO code for it.
     *
     * However, the amounts stored are in the shop's default currency, even if
     * another currency was presented to the customer, so we will not have to
     * convert the amounts and this meta info is thus purely informative.
     */
    public function getCurrency()
    {
        // Load the currency.
        /** @var \VirtueMartModelCurrency $currency_model */
        $currency_model = VmModel::getModel('currency');
        /** @var \TableCurrencies $currency */
        $currency = $currency_model->getCurrency($this->source['details']['BT']->user_currency_id);
        $result = array (
            Meta::Currency => $currency->currency_code_3,
            Meta::CurrencyRate => (float) $this->source['details']['BT']->user_currency_rate,
            Meta::CurrencyDoConvert => false,
        );
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount as they may be needed by the Completor.
     */
    public function getTotals()
    {
        return array(
            Meta::InvoiceAmountInc => $this->source['details']['BT']->order_total,
            Meta::InvoiceVatAmount => $this->source['details']['BT']->order_billTaxAmount,
        );
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
     * {@inheritdoc}
     */
    public function getInvoiceReference()
    {
        return !empty($this->invoice['invoice_number']) ? $this->invoice['invoice_number'] : parent::getInvoiceReference();
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDate()
    {
        return !empty($this->invoice['created_on']) ? date(API::DateFormat_Iso, strtotime($this->invoice['created_on'])) : parent::getInvoiceDate();
    }
}
