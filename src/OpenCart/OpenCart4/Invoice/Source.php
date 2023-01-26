<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  many parent methods are
 *   no-ops or call {@see Source::callTypeSpecificMethod()}.
 * @noinspection DuplicatedCode  Remove when extracting code common for OC3 and OC4
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\OpenCart4\Helpers\Registry;

use function in_array;
use function strlen;

/**
 * Wraps an OpenCart order in an invoice source object.
 *
 * @property array $source
 */
class Source extends BaseSource
{
    /**
     * @var array[]
     *   List of OpenCart order total records.
     */
    protected array $orderTotalLines;

    /**
     * {@inheritdoc}
     */
    protected function setSource(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->source = $this->getRegistry()->getOrder($this->id);
    }

    /**
     * Sets the id based on the loaded Order.
     */
    protected function setId(): void
    {
        $this->id = $this->source['order_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): string
    {
        return substr($this->source['date_added'], 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getStatus(): int
    {
        return (int) $this->source['order_status_id'];
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode(): string
    {
        if (!empty($this->source['payment_iso_code_2'])) {
            return $this->source['payment_iso_code_2'];
        } elseif (!empty($this->source['shipping_iso_code_2'])) {
            return $this->source['shipping_iso_code_2'];
        } else {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the code of the selected payment method.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->source['payment_code'] ?? parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus(): int
    {
        // The 'config_complete_status' setting contains a set of statuses that,
        //  according to the help on the settings form:
        // "The order status the customer's order must reach before they are
        //  allowed to access their downloadable products and gift vouchers."
        // This seems like the set of statuses where payment has been
        // completed...
        $orderStatuses = (array) $this->getRegistry()->config->get('config_complete_status');

        return (empty($orderStatuses) || in_array($this->source['order_status_id'], $orderStatuses, true))
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate(): ?string
    {
        // @todo Can we determine this based on history (and optionally
        //   payment_code)?
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
    public function getCurrency(): array
    {
        return [
            Meta::Currency => $this->source['currency_code'],
            Meta::CurrencyRate => (float) $this->source['currency_value'],
            Meta::CurrencyDoConvert => false,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc,
     * meta-invoice-vatamount and a vat breakdown in meta-invoice-vat.
     */
    protected function getAvailableTotals(): array
    {
        $result = [
            Meta::InvoiceAmountInc => (float) $this->source['total'],
            Meta::InvoiceVatAmount => 0.0,
            Meta::InvoiceVatBreakdown => [],
        ];

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
     */
    public function getOrderTotalLines(): array
    {
        if (!isset($this->orderTotalLines)) {
            $this->orderTotalLines = $this->getOrderModel()->getTotals($this->source['order_id']);
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
     * @return \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order
     */
    protected function getOrderModel()
    {
        return $this->getRegistry()->getOrderModel();
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
