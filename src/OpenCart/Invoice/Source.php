<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Wraps an OpenCart order in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var array */
    protected $source;

    /** @var array[] List of OpenCart order total records. */
    protected $orderTotalLines = null;

    /**
     * {@inheritdoc}
     */
    protected function setSource()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->source = Registry::getInstance()->getOrder($this->id);
    }

    /**
     * Sets the id based on the loaded Order.
     */
    protected function setId()
    {
        $this->id = $this->source['order_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDate()
    {
        return substr($this->source['date_added'], 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->source['order_status_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        return !empty($this->source['payment_iso_code_2']) ? $this->source['payment_iso_code_2'] : '';
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the code of the selected payment method.
     */
    public function getPaymentMethod()
    {
        if (isset($this->source['payment_code'])) {
            return $this->source['payment_code'];
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus()
    {
        // The 'config_complete_status' setting contains a set of statuses that,
        //  according to the help on the settings form:
        // "The order status the customer's order must reach before they are
        //  allowed to access their downloadable products and gift vouchers."
        // This seems like the set of statuses where payment has been
        // completed...
        $orderStatuses = (array) $this->getRegistry()->config->get('config_complete_status');

        $result = (empty($orderStatuses) || in_array($this->source['order_status_id'], $orderStatuses)) ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate()
    {
        // @todo: Can we determine this based on history (and optionally payment_code)?
        // Will default to the issue date.
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * In OpenCart the amounts are in the shop's default currency, even if
     * another currency was presented to the customer, so we will not have to
     * convert the amounts and this meta info is thus purely informative.
     */
    public function getCurrency()
    {
        $result = array(
            Meta::Currency => $this->source['currency_code'],
            Meta::CurrencyRate => (float) $this->source['currency_value'],
            Meta::CurrencyDoConvert => false,
        );
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc,
     * meta-invoice-vatamount and a vat breakdown in meta-invoice-vat.
     */
    protected function getAvailableTotals()
    {
        $result = array(
            Meta::InvoiceAmountInc => $this->source['total'],
            Meta::InvoiceVatAmount => 0.0,
            Meta::InvoiceVatBreakdown => array(),
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $orderTotals = $this->getOrderTotalLines();
        foreach ($orderTotals as $totalLine) {
            if ($totalLine['code'] === 'tax') {
                $result[Meta::InvoiceVatBreakdown][] = $totalLine['title'] . ': ' . $totalLine['value'];
                $result[Meta::InvoiceVatAmount] += $totalLine['value'];
            }
        }
        return $result;
    }

    /**
     * Returns a list of OpenCart order total records.
     *
     * These are shipment, other fee, tax, and discount lines.
     *
     * @return array[]
     *   The set of order total lines for this order. This set is ordered by
     *   sort_order, meaning that lines before the tax line are amounts ex vat
     *   and lines after are inc vat.
     *
     * @throws \Exception
     */
    public function getOrderTotalLines()
    {
        if (!$this->orderTotalLines) {
            $orderModel = $this->getRegistry()->getOrderModel();
            $this->orderTotalLines = $orderModel->getOrderTotals($this->source['order_id']);
        }
        return $this->orderTotalLines;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReference()
    {
        $result = null;
        if (!empty($this->source['invoice_no'])) {
            $result = $this->source['invoice_prefix'] . $this->source['invoice_no'];
        }
        return $result;
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     *
     * @return \Siel\Acumulus\OpenCart\Helpers\Registry
     *
     */
    protected function getRegistry()
    {
        return Registry::getInstance();
    }
}
