<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Configuration;
use Context;
use Country;
use Currency;
use DateTimeImmutable;
use DateTimeInterface;
use Db;
use Order;
use OrderSlip;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;
use PrestaShop\PrestaShop\Core\Version;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency as AcumulusCurrency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;

use Siel\Acumulus\Meta;
use Validate;

use function is_array;
use function sprintf;

/**
 * Wraps a PrestaShop order in an invoice source object.
 *
 * @method Order|OrderSlip getShopObject()
 */
class Source extends BaseSource
{
    /**
     * {@inheritdoc}
     *
     * @throws \PrestaShopException
     */
    protected function setShopObject(): void
    {
        if ($this->getType() === Source::Order) {
            $this->shopObject = new Order($this->getId());
        } else {
            $this->shopObject = new OrderSlip($this->getId());
            $this->addProperties();
        }
        if (!Validate::isLoadedObject($this->shopObject)) {
            throw new RuntimeException(sprintf('Not a valid source id (%s %d)', $this->type, $this->id));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     *   This override returns the order reference, a sequence of characters, or the order
     *   slip reference, a configurable prefix plus the id with padding zeros.
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getReference(): string
    {
        return $this->getType() === Source::Order
            ? $this->getShopObject()->reference
            : sprintf('%s%06d', Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id), $this->getShopObject()->id);
    }

    /**
     * Sets id based on the loaded Order.
     *
     * @throws \PrestaShopDatabaseException|\PrestaShopException
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()->id;
        if ($this->getType() === Source::CreditNote) {
            $this->addProperties();
        }
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getDate(): DateTimeInterface
    {
        /**
         * @noinspection PhpUndefinedFieldInspection Property date_add is defined in both
         *   Order and OrderSlip, not in their common base class.
         */
        return new DateTimeImmutable($this->getShopObject()->date_add);
    }

    /**
     * Returns the status of this order.
     *
     * @noinspection PhpUnused Called via getStatus().
     */
    protected function getStatusOrder(): int|null
    {
        return isset($this->getShopObject()->current_state) ? (int) $this->getShopObject()->current_state : null;
    }

    /**
     * Returns the status of this credit note.
     *
     * @return null
     *   A credit note in PrestaShop does not have a state.
     *
     * @noinspection PhpUnused Called via getStatus().
     */
    protected function getStatusCreditNote(): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return ?string
     *   This override returns the name of the payment module.
     */
    public function getPaymentMethod(): ?string
    {
        /** @var \Order $order */
        $order = $this->getOrder()->shopObject;
        return $order->module ?? parent::getPaymentMethod();
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getPaymentStatus(): int
    {
        // Assumption: credit slips are always in a paid status.
        return ($this->getType() === Source::Order && $this->getShopObject()->hasBeenPaid()) || $this->getType() === Source::CreditNote
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getPaymentDate(): ?DateTimeInterface
    {
        if ($this->getType() === Source::Order) {
            $paymentDate = null;
            /** @var \Order $order */
            $order = $this->getShopObject();
            foreach ($order->getOrderPaymentCollection() as $payment) {
                /** @var \OrderPayment $payment */
                if (!empty($payment->date_add) && ($paymentDate === null || $payment->date_add > $paymentDate)) {
                    $paymentDate = $payment->date_add;
                }
            }
        } else {
            // Assumption: the last modified date is the date of the actual reimbursement.
            /**
             * @noinspection PhpUndefinedFieldInspection Property date_add is defined in
             *   both Order and OrderSlip, not in their common base class.
             */
            $paymentDate = $this->getShopObject()->date_upd;
        }

        return $paymentDate ? new DateTimeImmutable($paymentDate) : null;
    }

    public function getCountryCode(): string
    {
        $invoiceAddress = new Address($this->getOrder()->shopObject->id_address_invoice);
        return !empty($invoiceAddress->id_country) ? Country::getIsoById($invoiceAddress->id_country) : '';
    }

    /**
     * {@inheritdoc}
     *
     * PrestaShop stores the internal currency id, so look up the currency
     * object first, then extract the ISO code for it.
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getCurrency(): AcumulusCurrency
    {
        $currency = Currency::getCurrencyInstance($this->getOrder()->shopObject->id_currency);
        /**
         * @noinspection PhpUndefinedFieldInspection The property conversion_rate exists
         *   in both Order and OrderSlip
         */
        return new AcumulusCurrency($currency->iso_code, 1.0 / $this->getShopObject()->conversion_rate, true);
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values 'meta-invoice-amountinc' and
     * 'meta-invoice-amount' for PrestaShop
     *
     * @throws \PrestaShopException
     */
    public function getTotals(): Totals
    {
        $sign = $this->getSign();
        if ($this->getType() === Source::Order) {
            /** @var Order $order */
            $order = $this->getShopObject();
            $amountEx = $order->getTotalProductsWithoutTaxes()
                + $order->total_shipping_tax_excl
                + $order->total_wrapping_tax_excl
                - $order->total_discounts_tax_excl;
            $amountInc = $order->getTotalProductsWithTaxes()
                + $order->total_shipping_tax_incl
                + $order->total_wrapping_tax_incl
                - $order->total_discounts_tax_incl;
        } else {
            // On credit notes with order_slip_type = VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND:
            // - The amount incl. VAT will not have been corrected for discounts that are
            //   revoked on the refund.
            // - The amount excl. VAT has been corrected with the discount amount incl. VAT!
            //
            // On credit notes with order_slip_type = VoucherRefundType::SPECIFIC_AMOUNT_REFUND:
            // - The amount excl. VAT will have been corrected with the discount amount
            //   incl. VAT!.
            // - The total amount incl. that will be refunded will NOT contain any discount
            //   amount ... (as if the specific amount refunded specified = 0,00)
            // So to follow the shop, we will have to treat this as if no voucher has been
            // revoked.
            //
            // Use the cart rules to correct these errors.
            /** @var OrderSlip $creditNote */
            $creditNote = $this->getShopObject();
            $amountEx = $creditNote->total_products_tax_excl + $creditNote->total_shipping_tax_excl;
            $amountInc = $creditNote->total_products_tax_incl + $creditNote->total_shipping_tax_incl;
            /** @noinspection PhpCastIsUnnecessaryInspection  order_slip_type contains the string representation of an integer */
            if ((int) $creditNote->order_slip_type !== VoucherRefundType::PRODUCT_PRICES_REFUND) {
                /** @var Order $order */
                $order = $this->getOrder()->getShopObject();
                /** @var \OrderCartRule[] $cartRules */
                $cartRules = $order->getCartRules();
                /** @var array $cartRule */
                foreach ($cartRules as $cartRule) {
                    /** @noinspection PhpCastIsUnnecessaryInspection  order_slip_type contains the string representation of an integer */
                    if ((int) $creditNote->order_slip_type === VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND) {
                        $amountEx += $cartRule['value'] - $cartRule['value_tax_excl'];
                        $amountInc -= $cartRule['value'];
                    } else {
                        $amountEx += $cartRule['value'];
                    }
                }
            }
        }
        $totals = new Totals($sign * $amountInc, null, $sign * $amountEx);
        foreach ($this->getPaymentFeeLineInfos() as $paymentFeeLineInfo) {
            $totals->add(
                $paymentFeeLineInfo->metadataGet(Meta::UnitPriceInc),
                $paymentFeeLineInfo->metadataGet(Meta::VatAmount),
                $paymentFeeLineInfo->unitPrice,
                $paymentFeeLineInfo->vatRate
            );
        }
        return $totals;
    }

    /**
     * Returns the invoice reference for an order
     *
     * @noinspection PhpUnused  Called via {@see Source::getInvoiceReference()}.
     */
    protected function getInvoiceReferenceOrder(): ?string
    {
        /** @var \Order $order */
        $order = $this->getShopObject();
        /**
         * @noinspection PhpCastIsUnnecessaryInspection Despite the type documentation,
         *   the id_land is returned as a string.
         */
        return !empty($order->invoice_number)
            ? sprintf(
                '%s%06d',
                Configuration::get('PS_INVOICE_PREFIX', (int) $order->id_lang, null, $order->id_shop),
                $order->invoice_number
            )
            : null;
    }

    /**
     * Returns the invoice date for an order
     *
     * @noinspection PhpUnused  Called via getInvoiceDate().
     */
    protected function getInvoiceDateOrder(): ?DateTimeInterface
    {
        return !empty($this->getShopObject()->invoice_number)
            ? new DateTimeImmutable($this->getShopObject()->invoice_date)
            : null;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    protected function getShopOrderOrId(): int
    {
        /** @var \OrderSlip $orderSlip */
        $orderSlip = $this->shopObject;
        /** @noinspection PhpCastIsUnnecessaryInspection Despite the documented return
         *   type, id is returned as a string.
         */
        return (int) $orderSlip->id_order;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    protected function getShopCreditNotesOrIds(): iterable
    {
        /** @var \Order $order */
        $order = $this->shopObject;
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
     * @throws \PrestaShopDatabaseException|\PrestaShopException
     */
    protected function addProperties(): void
    {
        if (version_compare(Version::VERSION, '1.7.5', '<')) {
            $row = Db::getInstance()->executeS(
                sprintf(
                    'SELECT * FROM `%s` WHERE `%s` = %u',
                    _DB_PREFIX_ . OrderSlip::$definition['table'],
                    OrderSlip::$definition['primary'],
                    $this->getId()
                )
            );
            if (is_array($row)) {
                // Get the 1st (and only) result if no error
                $row = reset($row);
                foreach ($row as $key => $value) {
                    /** @noinspection PhpVariableVariableInspection */
                    $this->getShopObject()->$key ??= $value;
                }
            }
        }
    }

    /**
     * Returns records from several joined tables containing item line information.
     *
     * {@see Order::getOrderDetailTaxes()} returns joined records from the tables:
     * - order_detail (order lines)
     * - order_detail_tax (tax details for order lines)
     * - tax (tax classes)
     *
     * {@see OrderSlip::getOrdersSlipDetail()} returns joined records from the tables:
     * - order_slip_detail (credit note lines, with column 'id_order_detail' referring to
     *   order lines (not a counter within an order slip).
     *
     * - Source::Order: order_detail_tax.
     * - Source::CreditNote: order_slip_detail + order_detail_tax, the latter only for
     *   order details that are refunded and thus are referenced in order_slip_detail.
     *
     * See {@see \Siel\Acumulus\PrestaShop\Invoice\Item} for more information.
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    protected function createItems(): array
    {
        if ($this->getType() === Source::Order) {
            $orderDetails = $this->mergeProductLines(
                $this->getShopObject()->getProductsDetail(),
                $this->getShopObject()->getOrderDetailTaxes()
            );
        } else {
            $orderDetails = $this->mergeProductLines(
                OrderSlip::getOrdersSlipProducts($this->getId(), $this->getOrder()->getShopObject()),
                $this->getOrder()->getShopObject()->getOrderDetailTaxes()
            );
        }
        $items = [];
        foreach ($orderDetails as $orderDetail) {
            $items[] = $this->getContainer()->createItem($orderDetail, $this);
        }
        return $items;
    }

    /**
     * Merges arrays of records on the field 'id_order_detail'.
     *
     * @param array $productLines
     *   An array of order (slip) line records ((order_slip_detail +) order_detail in PS).
     * @param array $taxLines
     *   An array of tax information records (order_detail_tax + tax in PS).
     *
     * @return array
     *   An array with the product and tax records joined on the field 'id_order_detail',
     *   the unique identifier for an order line.
     */
    protected function mergeProductLines(array $productLines, array $taxLines): array
    {
        // Re-index the product lines on id_order_detail, so we can easily add the tax
        // lines.
        $result = array_column($productLines, null, 'id_order_detail');
        $alreadyAdded = [];
        // Add the tax lines without overwriting existing entries (though in a
        // consistent db the same keys should contain the same values).
        foreach ($taxLines as $taxLine) {
            if (isset($result[$taxLine['id_order_detail']])) {
                if (isset($alreadyAdded[$taxLine['id_order_detail']])) {
                    // A 2nd tax line for a product line: not common in the Netherlands
                    Container::getContainer()->getLog()->notice(
                        sprintf(
                            '%s: Another tax detail found for order item line %d (of %s %d)',
                            __METHOD__,
                            $taxLine['id_order_detail'],
                            $this->t($this->getType()),
                            $this->getId()
                        )
                    );
                } else {
                    $result[$taxLine['id_order_detail']] += $taxLine;
                    $alreadyAdded[$taxLine['id_order_detail']] = true;
                }
            } else {
                // We have a tax line for a non-product item line ([SIEL #200452]).
                // This is to be expected for credit notes where not all products were
                // returned, but not for orders.
                if ($this->getType() === Source::Order) {
                    Container::getContainer()->getLog()->notice(
                        sprintf(
                            '%s: Tax detail found for order item line %d (of %s %d) without product info',
                            __METHOD__,
                            $taxLine['id_order_detail'],
                            $this->t($this->getType()),
                            $this->getId()
                        )
                    );
                }
            }
        }
        return $result;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getShippingLineInfos(): array
    {
        return !empty($this->getOrder()->getShopObject()->id_carrier) ? [$this] : [];
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getGiftWrappingFeeLineInfos(): array
    {
        $order = $this->getShopObject();
        return $this->getType() === Source::Order && $order->gift && !Number::isZero($order->total_wrapping_tax_incl)
            ? [$this]
            : [];
    }

    /**
     * {@inheritdoc}
     *
     * This override checks if the fields 'payment_fee' and 'payment_fee_rate'
     * are set and, if so, uses them to add a payment fee line.
     *
     * These fields are set by the "PayPal with a fee" module but seem generic
     * enough to also be used by other modules that allow for payment fees.
     *
     * For now, only orders can have a payment fee.
     *
     * @return Line[]
     *
     * @throws \PrestaShopException
     *
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    public function getPaymentFeeLineInfos(): array
    {
        return (new ThirdPartyModuleSupport($this))->getPaymentFeeLineInfos();
    }

    /**
     * {@inheritdoc}
     *
     * A PrestaShop order stores the discounts applied in order cart rules.
     *
     * In a Prestashop credit slip, the discounts are not directly visible but can be
     * retrieved by looking at the cart rules from the original order. However, the field
     * {@see OrderSlip::$order_slip_type} indicates if the cart rules from that order are
     * to be revoked ({@see VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND})
     * or should remain ({@see VoucherRefundType::PRODUCT_PRICES_REFUND}).
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    public function getDiscountLineInfos(): array
    {
        $result = [];
        if ($this->getType() === Source::Order
            || (int) $this->getShopObject()->order_slip_type === VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND
        ) {
            $result = $this->getOrder()->getShopObject()->getCartRules();
        }
        return $result;
    }
}
