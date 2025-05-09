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
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;
use PrestaShop\PrestaShop\Core\Version;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency as AcumulusCurrency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;

use Validate;

use function is_array;
use function sprintf;
use function strlen;

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
     */
    public function getReference(): string
    {
        return $this->getType() === Source::Order
            ? $this->getShopObject()->reference
            : Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id) . sprintf(
                '%06d',
                $this->getShopObject()->id
            );
    }

    /**
     * Sets id based on the loaded Order.
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function setId(): void
    {
        $this->id = $this->getShopObject()->id;
        if ($this->getType() === Source::CreditNote) {
            $this->addProperties();
        }
    }

    public function getDate(): string
    {
        return substr($this->getShopObject()->date_add, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the status of this order.
     *
     * @noinspection PhpUnused
     *   Called via getStatus().
     */
    protected function getStatusOrder(): int|null
    {
        return isset($this->getShopObject()->current_state) ? (int) $this->getShopObject()->current_state : null;
    }

    /**
     * Returns the status of this credit note.
     *
     * @noinspection PhpDocSignatureInspection PHP 8.2: null is a standalone type
     * @return null
     *   A credit note in PrestaShop does not have a state.
     *
     * @todo: PHP8.2: standalone null is allowed
     * @noinspection PhpUnused Called via getStatus().
     */
    protected function getStatusCreditNote(): int|null
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

    public function getPaymentStatus(): int
    {
        // Assumption: credit slips are always in a paid status.
        return ($this->getType() === Source::Order && $this->getShopObject()->hasBeenPaid()) || $this->getType() === Source::CreditNote
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
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
            // Assumption: last modified date is the date of the actual reimbursement.
            $paymentDate = $this->getShopObject()->date_upd;
        }

        return $paymentDate ? substr($paymentDate, 0, strlen('2000-01-01')) : null;
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
     */
    public function getCurrency(): AcumulusCurrency
    {
        $currency = Currency::getCurrencyInstance($this->getOrder()->shopObject->id_currency);
        /** @noinspection PhpCastIsUnnecessaryInspection  conversion_rate contains the string representation of a float */
        return new AcumulusCurrency($currency->iso_code, 1.0 / $this->getShopObject()->conversion_rate, true);
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-amount for PrestaShop
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
            // So to follow the shop we will have to treat this as if no voucher has been
            // revoked.
            //
            // Use the cart rules to correct these errors.
            /** @var OrderSlip $creditNote */
            $creditNote = $this->getShopObject();
            $amountEx = $creditNote->total_products_tax_excl
                + $creditNote->total_shipping_tax_excl;
            $amountInc = $creditNote->total_products_tax_incl
                + $creditNote->total_shipping_tax_incl;
            /** @noinspection PhpCastIsUnnecessaryInspection  order_slip_type contains the string representation of an integer */
            if ((int) $creditNote->order_slip_type !== VoucherRefundType::PRODUCT_PRICES_REFUND) {
                /** @var Order $order */
                $order = $this->getOrder()->getShopObject();
                /** @var \OrderCartRule[] $cartRules */
                $cartRules = $order->getCartRules();
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

        return new Totals($sign * $amountInc, null, $sign * $amountEx);
    }

    /**
     * Returns the invoice reference for an order
     *
     * @noinspection PhpUnused  Called via getInvoiceReference().
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
            ? Configuration::get('PS_INVOICE_PREFIX', (int) $order->id_lang, null, $order->id_shop)
            . sprintf('%06d', $order->invoice_number)
            : null;
    }

    /**
     * Returns the invoice date for an order
     *
     * @noinspection PhpUnused  Called via getInvoiceDate().
     */
    protected function getInvoiceDateOrder(): ?string
    {
        return !empty($this->getShopObject()->invoice_number)
            ? substr($this->getShopObject()->invoice_date, 0, strlen('2000-01-01'))
            : null;
    }

    protected function getShopOrderOrId(): int
    {
        /** @var \OrderSlip $orderSlip */
        $orderSlip = $this->shopObject;
        /** @noinspection PhpCastIsUnnecessaryInspection Despite the documented return
         *   type, id is returned as a string.
         */
        return (int) $orderSlip->id_order;
    }

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
     * @throws \PrestaShopDatabaseException
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
                // Get 1st (and only) result if no error
                $row = reset($row);
                foreach ($row as $key => $value) {
                    /** @noinspection PhpVariableVariableInspection */
                    $this->getShopObject()->$key ??= $value;
                }
            }
        }
    }

    /**
     * Returns records from:
     * - Source::Order: order detail table + order_detail_tax.
     * - Source::CreditNote: order_slip_detail + order_detail.
     */
    protected function createItems(): array
    {
        if ($this->getType() === Source::Order) {
            // Note: these methods return "raw" (and merged) database results, not objects
            //   from the PrestaShop datamodel.
            $orderDetails = $this->mergeProductLines(
                $this->getShopObject()->getProductsDetail(),
                $this->getShopObject()->getOrderDetailTaxes()
            );
        } else {
            $orderDetails = OrderSlip::getOrdersSlipProducts($this->getId(), $this->getOrder()->getShopObject());
        }
        $items = [];
        foreach ($orderDetails as $orderDetail) {
            $items[] = $this->getContainer()->createItem($orderDetail, $this);
        }
        return $items;
    }

    /**
     * Merges the product and tax details arrays.
     *
     * @param array $productLines
     *   An array of order line information, the fields are about the product of this
     *   order line.
     * @param array $taxLines
     *   An array of line tax information, the fields are about the tax on this
     *   order line.
     *
     * @return array
     *   An array with the product and tax lines merged based on the field
     *   'id_order_detail', the unique identifier for an order line.
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
                            '%s: Another tax detail found for order item line %d (of order %d)',
                            __METHOD__,
                            $taxLine['id_order_detail'],
                            $this->getId()
                        )
                    );
                } else {
                    $result[$taxLine['id_order_detail']] += $taxLine;
                    $alreadyAdded[$taxLine['id_order_detail']] = true;
                }
            } else {
                // We have a tax line for a non-product item line ([SIEL #200452]).
                Container::getContainer()->getLog()->notice(
                    sprintf(
                        '%s: Tax detail found for order item line %d (of order %d) without product info',
                        __METHOD__,
                        $taxLine['id_order_detail'],
                        $this->getId()
                    )
                );
                $result[$taxLine['id_order_detail']] = $taxLine;
            }
        }
        return $result;
    }

    public function getShippingLineInfos(): array
    {
        return !empty($this->getOrder()->getShopObject()->id_carrier) ? [$this] : [];
    }

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
     */
    public function getPaymentFeeLineInfos(): array
    {
        $order = $this->getShopObject();
        /**
         * @noinspection MissingIssetImplementationInspection These fields are set by
         *   the module "PayPal with a fee".
         */
        return isset($order->payment_fee, $order->payment_fee_rate) && (float) $order->payment_fee !== 0.0 ? [$this] : [];
    }

    /**
     * Description.
     *
     * A PrestaShop order stores the discounts applied in order cart rules.
     *
     * In a Prestashop credit slip, the discounts are not directly visible but can be
     * retrieved by looking at the cart rules form the original order. However, the field
     * {@see OrderSlip::$order_slip_type} indicates if the cart rules from that order are
     * to be revoked ({@see VoucherRefundType::PRODUCT_PRICES_EXCLUDING_VOUCHER_REFUND})
     * or should remain ({@see VoucherRefundType::PRODUCT_PRICES_REFUND}).
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
