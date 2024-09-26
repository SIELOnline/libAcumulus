<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  many parent methods are
 *   no-ops or call {@see Source::callTypeSpecificMethod()}.
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Invoice;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\OpenCart\Helpers\Registry;

use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Wraps an OpenCart order in an invoice source object.
 *
 * @property array $shopObject
 * @method array getShopObject()
 */
abstract class Source extends BaseSource
{
    // Known codes:
    // - fees: shipping, handling, low_order_fee
    // - "totals" (ignored): sub_total, tax, total
    // - "discounts":  coupon, voucher
    // - other (ignored): credit, reward (reward points)
    public const LineTypeToCode = [
        LineType::Shipping => 'shipping',
        LineType::Other => ['handling', 'low_order_fee'],
        LineType::Discount => 'coupon',
        LineType::Voucher => 'voucher',
    ];

    public const Vat_Excluded = 'ex-vat';
    public const Vat_IsVat = 'is-vat';
    public const Vat_Included = 'inc-vat';

    /**
     * @var array[]
     *   List of OpenCart order total records.
     */
    protected array $orderTotalLines;

    /**
     * @throws \Exception
     */
    protected function setShopObject(): void
    {
        $order = $this->getRegistry()->getOrder($this->getId());
        if (empty($order)) {
            throw new RuntimeException(sprintf('Order %d not found', $this->getId()));
        }
        $this->shopObject = $order;
    }

    /**
     * Sets the id based on the loaded Order.
     */
    protected function setId(): void
    {
        $this->id = $this->shopObject['order_id'];
    }

    public function getDate(): string
    {
        return substr($this->shopObject['date_added'], 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * @return int|null
     *   The id of the status.
     */
    public function getStatus(): int|null
    {
        return isset($this->shopObject['order_status_id']) ? (int) $this->shopObject['order_status_id'] : null;
    }

    public function getCountryCode(): string
    {
        if (!empty($this->shopObject['payment_iso_code_2'])) {
            return $this->shopObject['payment_iso_code_2'];
        } elseif (!empty($this->shopObject['shipping_iso_code_2'])) {
            return $this->shopObject['shipping_iso_code_2'];
        } else {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return ?string
     *   This override returns the code of the selected payment method.
     */
    public function getPaymentMethod(): ?string
    {
        if (is_array($this->shopObject['payment_method'])) {
            // Spotted in OC4 2024.
            $code = $this->shopObject['payment_method']['code'];
            if (str_contains($code, '.')) {
                $code = substr($code, 0, strpos($code, '.'));
            }
        } elseif (!empty($this->shopObject['payment_custom_field']) && is_array($this->shopObject['payment_custom_field'])) {
            // Spotted in OC4 2023.
            $code = $this->shopObject['payment_custom_field']['code'];
        } elseif (!empty($this->shopObject['payment_code'])) {
            // Spotted in OC3.
            $code = $this->shopObject['payment_code'];
        }
        return $code ?? null;
    }

    public function getPaymentStatus(): int
    {
        // The 'config_complete_status' setting contains a set of statuses that,
        //  according to the help on the settings form:
        // "The order status the customer's order must reach before they are
        //  allowed to access their downloadable products and gift vouchers."
        // This seems like the set of statuses where payment has been
        // completed...
        $orderStatuses = (array) $this->getRegistry()->config->get('config_complete_status');

        return (empty($orderStatuses) || in_array($this->shopObject['order_status_id'], $orderStatuses, true))
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

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
    public function getCurrency(): Currency
    {
        return new Currency($this->shopObject['currency_code'], (float) $this->shopObject['currency_value']);
    }

    public function getTotals(): Totals
    {
        $vatAmount = 0.0;
        $orderTotals = $this->getOrderTotalLines('tax');
        foreach ($orderTotals as $totalLine) {
            $vatAmount += $totalLine['value'];
        }
        return new Totals((float) $this->shopObject['total'], $vatAmount, null);
    }

    public function getVatBreakdown(): array
    {
        $vatBreakdown = [];
        $orderTotals = $this->getOrderTotalLines('tax');
        foreach ($orderTotals as $totalLine) {
            $vatBreakdown[$totalLine['title']] = $totalLine['value'];
        }
        return $vatBreakdown;
    }

    /**
     * {@inheritdoc}
     *
     * @return ?string
     *   A prefix followed by the invoice number, or null if not (yet) set.
     */
    public function getInvoiceReference(): ?string
    {
        $result = null;
        if (!empty($this->shopObject['invoice_no'])) {
            $result = $this->shopObject['invoice_prefix'] . $this->shopObject['invoice_no'];
        }
        return $result;
    }

    protected function createItems(): array
    {
        $result = [];
        foreach ($this->getOrderProducts() as $orderProduct) {
            $result[] = $this->getContainer()->createItem($this, $orderProduct);
        }
        return $result;
    }

    /**
     * Returns a list of OpenCart order total records.
     *
     * These are shipment, other fee, tax, and discount lines.
     *
     * @param string|array $code
     *   Specifies the type(s) of order total lines to return. If empty, all are returned.
     *
     * @return array[]
     *   The set of order total lines for this order. This set is ordered by
     *   sort_order, meaning that lines before the tax line are amounts ex vat
     *   and lines after are inc vat.
     *   If a $$code is passed, the set is filtered by the given code.
     */
    public function getOrderTotalLines(string|array $code = ''): array
    {
        if (!isset($this->orderTotalLines)) {
            $this->orderTotalLines = $this->_getOrderTotalLines();
            $vat = Source::Vat_Excluded;
            foreach ($this->orderTotalLines as &$orderTotalLine) {
                if ($orderTotalLine['code'] === 'tax') {
                    $vat = Source::Vat_IsVat;
                } elseif ($vat === Source::Vat_IsVat) {
                    $vat = Source::Vat_Included;
                }
                $orderTotalLine['vat'] = $vat;
            }
        }
        $result = $this->orderTotalLines;
        if (!empty($code)) {
            $result = array_filter($this->orderTotalLines, static function ($line) use ($code) {
                return is_string($code) ? $line['code'] === $code : in_array($line['code'], $code, true);
            });
        }
        return $result;
    }

    /**
     * Internal and version specific method to retrieve the order total lines.
     */
    abstract protected function _getOrderTotalLines(): array;

    /**
     * Returns a list of OpenCart order product line records.
     *
     * @return array[]
     *   An array with records from the order_product table.
     */
    abstract protected function getOrderProducts(): array;

    /**
     * {@inheritdoc}
     *
     * In OpenCart shipping info is stored as (an) order total line(s).
     */
    public function getShippingLineInfos(): array
    {
        return $this->getOrderTotalLines(static::LineTypeToCode[LineType::Shipping]);
    }

    /**
     * {@inheritdoc}
     *
     * In OpenCart fees are stored as order total lines (with code 'handling' or 'low_order_fee'.
     */
    public function getOtherLineInfos(): array
    {
        return $this->getOrderTotalLines(static::LineTypeToCode[LineType::Other]);
    }

    /**
     * {@inheritdoc}
     *
     * In OpenCart discounts are stored as an order total line.
     */
    public function getDiscountLineInfos(): array
    {
        return $this->getOrderTotalLines(static::LineTypeToCode[LineType::Discount]);
    }

    /**
     * {@inheritdoc}
     *
     * In OpenCart vouchers are stored as an order total line.
     */
    public function getVoucherLineInfos(): array
    {
        return $this->getOrderTotalLines(static::LineTypeToCode[LineType::Voucher]);
    }

    /**
     * @return \Opencart\Catalog\Model\Checkout\Order|\Opencart\Admin\Model\Sale\Order|\ModelCheckoutOrder|\ModelSaleOrder
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
