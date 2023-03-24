<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 *
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Carrier;
use Configuration;
use Customer;
use Exception;
use Order;
use OrderSlip;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use TaxManagerFactory;
use TaxRulesGroup;

use function array_slice;

/**
 * Creates a raw version of the Acumulus invoice from a PrestaShop {@see Source}.
 *
 * Notes:
 * - If needed, PrestaShop allows us to get tax rates by querying the tax table
 *   because as soon as an existing tax rate gets updated it will get a new id,
 *   so old order details still point to a tax record with the tax rate as was
 *   used at the moment the order was placed.
 * - Credit notes can get a correction line. They get one if the total amount
 *   does not match the sum of the lines added so far. This can happen if an
 *   amount was entered manually, or if discount(s) applied during the sale were
 *   subtracted from the credit amount, but we could not find which discounts
 *   this were. However:
 *   - amount is excl. vat if not manually entered.
 *   - amount is incl. vat if manually entered (assuming administrators enter
 *     amounts incl. tax) and this is what gets listed on the credit PDF.
 *   - Manually entered amounts do not have vat defined, so users should try not
 *     to use them.
 *   - shipping_cost_amount is excl. vat.
 *   So this is never going to work in all situations!!!
 *
 * @property \Siel\Acumulus\PrestaShop\Invoice\Source $invoiceSource
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Creator extends BaseCreator
{
    protected Order $order;
    protected OrderSlip $creditSlip;
    /**
     * Precision: 1 of the amounts, probably the prince incl tax, is entered by
     * the admin and can thus be considered exact. The other is calculated by
     * the system and not rounded and can thus be considered to have a precision
     * better than 0.0001.
     *
     * However, we have had a support call where the precision, for a credit
     * note, turned out to be only 0.002. This was, apparently, with a price
     * entered excl. vat: excl: 34,22; incl: 41,40378; (computed) vat: 7,18378.
     * The max-vat rate was just below 21%, so no match was made.
     */
    protected float $precision = 0.005;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource(\Siel\Acumulus\Invoice\Source $invoiceSource): void
    {
        parent::setInvoiceSource($invoiceSource);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                $this->creditSlip = $this->invoiceSource->getSource();
                $this->order = $this->invoiceSource->getShopOrder()->getShopSource();
                break;
        }
    }

    protected function setPropertySources(): void
    {
        parent::setPropertySources();
        $this->propertySources['address_invoice'] = new Address($this->order->id_address_invoice);
        $this->propertySources['address_delivery'] = new Address($this->order->id_address_delivery);
        $this->propertySources['customer'] = new Customer($this->invoiceSource->getSource()->id_customer);
    }

    protected function getItemLines(): array
    {
        $result = [];
        if ($this->invoiceSource->getType() === Source::Order) {
            // Note: getOrderDetailTaxes() is new in 1.6.1.0.
            $lines = method_exists($this->order, 'getOrderDetailTaxes')
                ? $this->mergeProductLines($this->order->getProductsDetail(), $this->order->getOrderDetailTaxes())
                : $this->order->getProductsDetail();
        } else {
            $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
        }

        foreach ($lines as $line) {
            $result[] = $this->getItemLine($line);
        }
        return $result;
    }

    /**
     * Merges the product and tax details arrays.
     *
     * @param array $productLines
     * @param array $taxLines
     *
     * @return array
     */
    public function mergeProductLines(array $productLines, array $taxLines): array
    {
        $result = [];
        // Key the product lines on id_order_detail, so we can easily add the
        // tax lines in the 2nd loop.
        foreach ($productLines as $productLine) {
            $result[$productLine['id_order_detail']] = $productLine;
        }
        // Add the tax lines without overwriting existing entries (though in a
        // consistent db the same keys should contain the same values).
        foreach ($taxLines as $taxLine) {
            $result[$taxLine['id_order_detail']] += $taxLine;
        }
        return $result;
    }

    /**
     * Returns 1 item line, both for an order or credit slip.
     *
     * @param array $item
     *   An array of an OrderDetail line combined with a tax detail line OR
     *   an array with an OrderSlipDetail line.
     *
     * @return array
     */
    protected function getItemLine(array $item): array
    {
        $result = [];

        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);
        $sign = $this->invoiceSource->getSign();

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $sign * $item['unit_price_tax_incl'];
        } else {
            $result[Tag::UnitPrice] = $sign * $item['unit_price_tax_excl'];
            $result[Meta::UnitPriceInc] = $sign * $item['unit_price_tax_incl'];
            $result[Meta::LineAmount] = $sign * $item['total_price_tax_excl'];
            $result[Meta::LineAmountInc] = $sign * $item['total_price_tax_incl'];
            // 'unit_amount' (table order_detail_tax) is not always set: assume
            // no discount if not set, so not necessary to add the value.
            if (isset($item['unit_amount']) &&
                !Number::floatsAreEqual($item['unit_amount'], $result[Meta::UnitPriceInc] - $result[Tag::UnitPrice])
            ) {
                $result[Meta::LineDiscountVatAmount] = $item['unit_amount'] - ($result[Meta::UnitPriceInc] - $result[Tag::UnitPrice]);
            }
        }
        $result[Tag::Quantity] = $item['product_quantity'];

        // Try to get the vat rate:
        // The field 'rate' comes from order->getOrderDetailTaxes() and is thus
        // only available for orders and was not filled before PS1.6.1.1. So,
        // check if the field is available.
        // The fields 'unit_amount' and 'total_amount' (also from table
        // order_detail_tax) are based on the discounted product price and thus
        // cannot be used to get the vat rate.
        if (isset($item['rate'])) {
            $result[Tag::VatRate] = $item['rate'];
            $result[Meta::VatRateSource] = Creator::VatRateSource_Exact;
        } else {
            $result += $this->getVatRangeTags($sign * ($item['unit_price_tax_incl'] - $item['unit_price_tax_excl']),
                $sign * $item['unit_price_tax_excl'],
                $this->precision, $this->precision);
        }
        $taxRulesGroupId = isset($item['id_tax_rules_group']) ? (int) $item['id_tax_rules_group'] : 0;
        $result += $this->getVatRateLookupMetadata($this->order->id_address_invoice, $taxRulesGroupId);

        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $result[Meta::FieldsCalculated][] = Meta::VatAmount;

        $this->removePropertySource('item');
        return $result;
    }

    protected function getShippingLine(): array
    {
        $sign = $this->invoiceSource->getSign();
        $carrier = new Carrier($this->order->id_carrier);
        // total_shipping_tax_excl is not very precise (rounded to the cent) and
        // often leads to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nicely rounded amount incl tax). So we
        // recalculate this ourselves.
        $vatRate = $this->order->carrier_tax_rate;
        $shippingInc = $sign * $this->invoiceSource->getSource()->total_shipping_tax_incl;
        $shippingEx = $shippingInc / (100 + $vatRate) * 100;
        $shippingVat = $shippingInc - $shippingEx;

        return [
            Tag::Product => !empty($carrier->name) ? $carrier->name : $this->t('shipping_costs'),
            Tag::UnitPrice => $shippingInc / (100 + $vatRate) * 100,
            Meta::UnitPriceInc => $shippingInc,
            Tag::Quantity => 1,
            Tag::VatRate => $vatRate,
            Meta::VatAmount => $shippingVat,
            Meta::VatRateSource => static::VatRateSource_Exact,
            Meta::FieldsCalculated => [Tag::UnitPrice, Meta::VatAmount],
               ] + $this->getVatRateLookupMetadata($this->order->id_address_invoice, $carrier->getIdTaxRulesGroup());
    }

    /**
     * {@inheritdoc}
     *
     * This override returns can return an invoice line for orders. Credit slips
     * cannot have a wrapping line.
     */
    protected function getGiftWrappingLine(): array
    {
        // total_wrapping_tax_excl is not very precise (rounded to the cent) and
        // can easily lead to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nicely rounded amount incl tax). So we
        // recalculate this ourselves by looking up the tax rate.
        $result = [];

        if ($this->invoiceSource->getType() === Source::Order && $this->order->gift && !Number::isZero($this->order->total_wrapping_tax_incl)) {
            /** @var string[] $metaCalculatedFields */
            $metaCalculatedFields = [];
            $wrappingEx = $this->order->total_wrapping_tax_excl;
            $wrappingExLookedUp = (float) Configuration::get('PS_GIFT_WRAPPING_PRICE');
            // Increase precision if possible.
            if (Number::floatsAreEqual($wrappingEx, $wrappingExLookedUp, 0.005)) {
                $wrappingEx = $wrappingExLookedUp;
                $metaCalculatedFields[] = Tag::UnitPrice;
                $precision = $this->precision;
            } else {
                $precision = 0.01;
            }
            $wrappingInc = $this->order->total_wrapping_tax_incl;
            $wrappingVat = $wrappingInc - $wrappingEx;
            $metaCalculatedFields[] = Meta::VatAmount;

            $vatLookupTags = $this->getVatRateLookupMetadata($this->order->id_address_invoice, (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP'));
            $result = [
                    Tag::Product => $this->t('gift_wrapping'),
                    Tag::UnitPrice => $wrappingEx,
                    Meta::UnitPriceInc => $wrappingInc,
                    Tag::Quantity => 1,
                      ] + $this->getVatRangeTags($wrappingVat, $wrappingEx, 0.01 + $precision, $precision)
                      + $vatLookupTags;
            $result[Meta::FieldsCalculated] = $metaCalculatedFields;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override checks if the fields 'payment_fee' and 'payment_fee_rate'
     * are set and, if so, uses them to add a payment fee line.
     *
     * These fields are set by the PayPal with a fee module but seem generic
     * enough to also be used by other modules that allow for payment fees.
     *
     * For now, only orders can have a payment fee, so $sign is superfluous,
     * but if in future versions payment fees can appear on order slips as well
     * the code can already handle that.
     */
    protected function getPaymentFeeLine(): array
    {
        /** @var Order|OrderSlip $source */
        $source = $this->invoiceSource->getSource();
        /** @noinspection MissingIssetImplementationInspection */
        if (isset($source->payment_fee, $source->payment_fee_rate) && (float) $source->payment_fee !== 0.0) {
            $sign = $this->invoiceSource->getSign();
            $paymentInc = $sign * $source->payment_fee;
            $paymentVatRate = (float) $source->payment_fee_rate;
            $paymentEx = $paymentInc / (100.0 + $paymentVatRate) * 100;
            $paymentVat = $paymentInc - $paymentEx;
            $result = [
              Tag::Product => $this->t('payment_costs'),
              Tag::Quantity => 1,
              Tag::UnitPrice => $paymentEx,
              Meta::UnitPriceInc => $paymentInc,
              Tag::VatRate => $paymentVatRate,
              Meta::VatRateSource => static::VatRateSource_Exact,
              Meta::VatAmount => $paymentVat,
              Meta::FieldsCalculated => [Tag::UnitPrice, Meta::VatAmount],
            ];

            /**
             * Add these amounts to the invoice totals.
             *{@see \Siel\Acumulus\Invoice\Source::getTotals()}
             */
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::InvoiceAmountInc] += $paymentInc;
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::InvoiceAmount] += $paymentEx;
            return $result;
        }
        return parent::getPaymentFeeLine();
    }


    /**
     * In a Prestashop order the discount lines are specified in Order cart
     * rules.
     *
     * @return array[]
     */
    protected function getDiscountLinesOrder(): array
    {
        $result = [];

        foreach ($this->order->getCartRules() as $line) {
            $result[] = $this->getDiscountLineOrder($line);
        }

        return $result;
    }

    /**
     * In a Prestashop order the discount lines are specified in Order cart
     * rules that have, a.o, the following fields:
     * - value: total amount inc VAT
     * - value_tax_excl: total amount ex VAT
     *
     * @param array $line
     *   A PrestaShop discount line (ie: an order_cart_rule record).
     *
     * @return array
     *   An Acumulus order item line.
     */
    protected function getDiscountLineOrder(array $line): array
    {
        $sign = $this->invoiceSource->getSign();
        $discountInc = -$sign * $line['value'];
        $discountEx = -$sign * $line['value_tax_excl'];
        $discountVat = $discountInc - $discountEx;
        $result = [
                Tag::ItemNumber => $line['id_cart_rule'],
                Tag::Product => $this->t('discount_code') . ' ' . $line['name'],
                Tag::UnitPrice => $discountEx,
                Meta::UnitPriceInc => $discountInc,
                Tag::Quantity => 1,
                // If no match is found, this line may be split.
                Meta::StrategySplit => true,
                // Assuming that the fixed discount amount was entered:
                // - including VAT, the precision would be 0.01, 0.01.
                // - excluding VAT, the precision would be 0.01, 0
                // However, for a %, it will be: 0.02, 0.01, so use 0.02.
                  ] + $this->getVatRangeTags($discountVat, $discountEx, 0.02, 0.01);
        $result[Meta::FieldsCalculated][] = Meta::VatAmount;

        return $result;
    }

    /**
     * In a Prestashop credit slip, the discounts are not visible anymore, but
     * can be computed by looking at the difference between the value of
     * total_products_tax_incl and the sum of the OrderSlipDetail amounts.
     *
     * @return array[]
     *
     * @noinspection PhpUnused : Called via getDiscountLines().
     */
    protected function getDiscountLinesCreditNote(): array
    {
        $result = [];

        // Get total amount credited.
        $creditSlipAmountInc = $this->creditSlip->total_products_tax_incl;

        // Get sum of product lines.
        $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
        $detailsAmountInc = array_reduce($lines, static function ($sum, $item) {
            $sum += $item['total_price_tax_incl'];
            return $sum;
        }, 0.0);

        // We assume that if total < sum(details), a discount given on the
        // original order has now been subtracted from the amount credited.
        if (!Number::floatsAreEqual($creditSlipAmountInc, $detailsAmountInc, 0.05)
            && $creditSlipAmountInc < $detailsAmountInc
        ) {
            // PS Error: total_products_tax_excl is not adjusted (whereas
            // total_products_tax_incl is) when a discount is subtracted from
            // the amount to be credited.
            // So we cannot calculate the discount ex VAT ourselves.
            // What we can try is the following: Get the order cart rules to see
            // if 1 or all of those match the discount amount here.
            $discountAmountInc = $detailsAmountInc - $creditSlipAmountInc;
            $totalOrderDiscountInc = 0.0;
            // Note: The sign of the entries in $orderDiscounts will be correct.
            $orderDiscounts = $this->getDiscountLinesOrder();

            foreach ($orderDiscounts as $key => $orderDiscount) {
                if (Number::floatsAreEqual($orderDiscount[Meta::UnitPriceInc], $discountAmountInc)) {
                    // Return this single line.
                    $from = $key;
                    $to = $key;
                    break;
                }
                $totalOrderDiscountInc += $orderDiscount[Meta::UnitPriceInc];
                if (Number::floatsAreEqual($totalOrderDiscountInc, $discountAmountInc)) {
                    // Return all lines up to here.
                    $from = 0;
                    $to = $key;
                    break;
                }
            }

            if (isset($from, $to)) {
                $result = array_slice($orderDiscounts, $from, $to - $from + 1);
                // Correct meta-invoice-amount.
                $totalOrderDiscountEx = array_reduce($result, static function ($sum, $item) {
                    $sum += $item[Tag::Quantity] * $item[Tag::UnitPrice];
                    return $sum;
                }, 0.0);
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::InvoiceAmount] += $totalOrderDiscountEx;
            } //else {
                // We could not match a discount with the difference between the
                // total amount credited and the sum of the products returned. A
                // manual line will correct the invoice.
            //}
        }
        return $result;
    }


    /**
     * Looks up and returns vat rate metadata.
     *
     * @param int $addressId
     * @param int $taxRulesGroupId
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId: int
     *   - Meta::VatClassName: string
     *   - Meta::VatRateLookup: float
     *   - Meta::VatRateLookupLabel: string
     */
    protected function getVatRateLookupMetadata(int $addressId, int $taxRulesGroupId): array
    {
        try {
            if (!empty($taxRulesGroupId)) {
                $taxRulesGroup = new TaxRulesGroup($taxRulesGroupId);
                $address = new Address($addressId);
                $taxManager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
                $taxCalculator = $taxManager->getTaxCalculator();
                $result = [
                    Meta::VatClassId => $taxRulesGroup->id,
                    Meta::VatClassName => $taxRulesGroup->name,
                    Meta::VatRateLookup => $taxCalculator->getTotalRate(),
                    Meta::VatRateLookupLabel => $taxCalculator->getTaxesName(),
                ];
            } else {
                $result = [
                    Meta::VatClassId => Config::VatClass_Null,
                ];
            }
        } catch (Exception $e) {
            $result = [];
        }
        return $result;
    }
}
