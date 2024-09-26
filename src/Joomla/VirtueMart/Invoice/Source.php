<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 *   Most parent methods are more or less empty stubs or return a default when
 *   the child does not override it.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use VmModel;

use function in_array;

/**
 * Wraps a VirtueMart order in an invoice source object.
 *
 * @method array getShopObject() a virtuemart_orders table record.
 * @property array $shopObject
 *   A virtuemart_orders table record.
 *
 *   Array with keys:
 *   [details]
 *     [BT]: stdClass (BillTo details)
 *     [ST]: stdClass (ShipTo details) (if available, copy of BT otherwise)
 *   [history]
 *     [0]: stdClass (virtuemart_order_histories table record)
 *     ...
 *   [items]
 *     [0]: stdClass (virtuemart_order_items table record)
 *     ...
 *   [calc_rules]
 *     [0]: stdClass (virtuemart_order_calc_rules table record)
 *     ...
 *
 * We might use the invoice:
 * @var \TableInvoices $invoicesTable
 * $invoicesTable = $this->orderModel->getTable('invoices');
 * if ($invoice = $invoicesTable->load($this->order['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
 *     $this->shopInvoice = $invoice->getProperties();
 * }
 * This results in an array with fields from the virtuemart_invoices table:
 *  - virtuemart_invoice_id
 *  - invoice_number
 *  - order_status
 *  - xhtml
 *  - + others
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
        /** @var \VirtueMartModelOrders $orders */
        $orders = VmModel::getModel('orders');
        $this->shopObject = $orders->getOrder($this->getId());
    }

    /**
     * Sets the id based on the loaded Order.
     *
     * @noinspection PhpUnused : called via setId().
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()['details']['BT']->virtuemart_order_id;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   A combination of letters and digits.
     */
    public function getReference(): string
    {
        return $this->getShopObject()['details']['BT']->order_number;
    }

    public function getDate(): string
    {
        return date(Api::DateFormat_Iso, strtotime($this->getShopObject()['details']['BT']->created_on));
    }

    /**
     * {@inheritdoc}
     *
     * @return string|null
     *   A single character indicating the order status.
     */
    public function getStatus(): string|null
    {
        return $this->getShopObject()['details']['BT']->order_status;
    }

    /**
     * {@inheritdoc}
     *
     * @return ?int
     *   The id of the payment method or null if not set (does not happen in our test
     *   orders)
     */
    public function getPaymentMethod(): ?int
    {
        return $this->getShopObject()['details']['BT']->virtuemart_paymentmethod_id ?? parent::getPaymentMethod();
    }

    public function getPaymentStatus(): int
    {
        return in_array($this->getShopObject()['details']['BT']->order_status, $this->getPaidStatuses(), false)
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
    {
        $date = null;
        $previousStatus = '';
        foreach ($this->getShopObject()['history'] as $orderHistory) {
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
        if (!empty($this->getShopObject()['details']['BT']->virtuemart_country_id)) {
            /** @var \VirtueMartModelCountry $countryModel */
            $countryModel = VmModel::getModel('country');
            $country = $countryModel->getData($this->getShopObject()['details']['BT']->virtuemart_country_id);
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
    public function getCurrency(): Currency
    {
        // Load the currency.
        /** @var \VirtueMartModelCurrency $currency_model */
        $currency_model = VmModel::getModel('currency');
        /** @var \TableCurrencies $currency */
        $currency = $currency_model->getCurrency($this->getShopObject()['details']['BT']->user_currency_id);
        return new Currency($currency->currency_code_3, (float) $this->getShopObject()['details']['BT']->user_currency_rate);
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount as they may be needed by the Completor.
     */
    public function getTotals(): Totals
    {
        return new Totals(
            (float) $this->getShopObject()['details']['BT']->order_total,
            (float) $this->getShopObject()['details']['BT']->order_billTaxAmount,
        );
    }

    /**
     * @inheritDoc
     */
    protected function setInvoice(): void
    {
        $orderModel = VmModel::getModel('orders');
        /** @var \TableInvoices $invoicesTable */
        $invoicesTable = $orderModel->getTable('invoices');
        if ($invoice = $invoicesTable->load($this->getShopObject()['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
            $this->invoice = $invoice->getProperties();
        }
    }

    /**
     * See {@see getInvoiceReference}
     *
     * @noinspection PhpUnused
     */
    protected function getInvoiceReferenceOrder()
    {
        return !empty($this->invoice['invoice_number']) ? $this->invoice['invoice_number'] : null;
    }

    /**
     * See {@see getInvoiceDate}
     *
     * @noinspection PhpUnused  Called via {@see callTypeSpecificMethod()}.
     */
    protected function getInvoiceDateOrder(): ?string
    {
        return !empty($this->invoice['created_on']) ? date(Api::DateFormat_Iso, strtotime($this->invoice['created_on'])) : null;
    }

    protected function createItems(): array
    {
        $result = [];
        foreach ($this->getShopObject()['items'] as $shopItem) {
            $result[] = $this->getContainer()->createItem($this, $shopItem);
        }
        return $result;
    }

    /**
     * We are checking on empty, assuming that a null value will be used to indicate no
     * shipping at all (downloadable product) and that free shipping will be represented
     * as the string '0.00' which is not considered empty.
     */
    public function getShippingLineInfos(): array
    {
        return !empty($this->getShopObject()['details']['BT']->order_shipment) ? [$this] : [];
    }

    public function getPaymentFeeLineInfos(): array
    {
        $result = [];
        if (!empty($this->getShopObject()['details']['BT']->order_payment)) {
            $paymentEx = (float) $this->getShopObject()['details']['BT']->order_payment;
            if (!Number::isZero($paymentEx)) {
                $result[] = $this;
            }
        }
        return $result;
    }

    public function getDiscountLineInfos(): array
    {
        $result = array_filter($this->getShopObject()['calc_rules'], [$this, 'isDiscountCalcRule']);
        if (!Number::isZero($this->getShopObject()['details']['BT']->coupon_discount)) {
            $result[] = $this;
        }
        return $result;
    }

    /**
     * Returns whether the calculation rule is a discount rule.
     *
     * @param object $calcRule
     *
     * @return bool
     *   True if the calculation rule is a discount rule.
     */
    protected function isDiscountCalcRule(object $calcRule): bool
    {
        return $calcRule->calc_amount < 0.0 && !in_array($calcRule->calc_kind, ['VatTax', 'shipment', 'payment']);
    }
}
