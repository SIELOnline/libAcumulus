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
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode  This is indeed a copy of the original Invoice\Creator.
 */

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Coupon;
use WC_Order_Item_Fee;

use function count;
use function in_array;

/**
 * Creates a raw version of the Acumulus invoice from a WooCommerce {@see Source}.
 *
 * @property \Siel\Acumulus\WooCommerce\Invoice\Source $invoiceSource
 */
class Creator extends BaseCreator
{
    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator and may be assumed exact. The computed one is based on the
     * subtraction/addition of 2 amounts, so has a precision that may be twice
     * as worse. WC tended to round amounts to the cent, but does not seem to
     * any longer do so.
     *
     * However, we still get reports of missed vat rates because they are out of
     * range, so we remain on the safe side and only use higher precision when
     * possible.
     */
    protected float $precision = 0.01;

    /**
     * @inheritdoc
     *
     * WooCommerce has general fee lines, so we have to override this method to
     * add these general fees (type unknown to us)
     */
    protected function getFeeLines(): array
    {
        $result = parent::getFeeLines();

        // So far, all amounts found on refunds are negative, so we probably
        // don't need to correct the sign on these lines either: but this has
        // not been tested yet!.
        foreach ($this->invoiceSource->getSource()->get_fees() as $feeLine) {
            $line = $this->getFeeLine($feeLine);
            $line = $this->addLineType($line, LineType::Other);
            $result[] = $line;
        }
        return $result;
    }

    /**
     * Returns an invoice line for 1 fee line.
     *
     * @param \WC_Order_Item_Fee $item
     *
     * @return array
     *   The invoice line for the given fee line.
     */
    protected function getFeeLine(WC_Order_Item_Fee $item): array
    {
        $quantity = $item->get_quantity();
        $feeEx = $item->get_total() / $quantity;
        $feeVat = $item->get_total_tax() / $quantity;

        return [
                Tag::Product => $this->t($item->get_name()),
                Tag::UnitPrice => $feeEx,
                Tag::Quantity => $item->get_quantity(),
                Meta::Id => $item->get_id(),
            ] + self::getVatRangeTags($feeVat, $feeEx, $this->precision, $this->precision);
    }

    protected function getDiscountLines(): array
    {
        $result = [];

        // For refunds without any articles (probably just a manual refund) we
        // don't need to know what discounts were applied on the original order.
        // So skip get_used_coupons() on refunds without articles.
        if ($this->invoiceSource->getType() !== Source::CreditNote || count($this->invoice->getLines()) > 0) {
            // Add a line for all coupons applied. Coupons are only stored on
            // the order, not on refunds, so use the order.
            /** @var \WC_Order $order */
            $order = $this->invoiceSource->getOrder()->getSource();
            $usedCoupons = $order->get_coupon_codes();
            foreach ($usedCoupons as $code) {
                $coupon = new WC_Coupon($code);
                $result[] = $this->getDiscountLine($coupon);
            }
        }
        return $result;
    }

    /**
     * Returns 1 order discount line for 1 coupon usage.
     *
     * In WooCommerce, discounts are implemented with coupons. Multiple coupons
     * can be used per order. Coupons can:
     * - have a fixed amount or a percentage.
     * - be applied to the whole cart or only be used for a set of products.
     *
     * Discounts are already applied, we just add a descriptive line with 0 amount. The
     * VAT rate to categorize this line under should be determined by the completor.
     *
     * @param \WC_Coupon $coupon
     *
     * @return array
     */
    protected function getDiscountLine(WC_Coupon $coupon): array
    {
        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        $couponId = $coupon->get_id();
        if ($couponId) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), ['fixed_product', 'fixed_cart'])) {
                $amount = $this->invoiceSource->getSign() * $coupon->get_amount();
                if (!Number::isZero($amount)) {
                    $description .= sprintf(
                        '€%.2f (%s)',
                        $amount,
                        $this->productPricesIncludeTax() ? $this->t('inc_vat') : $this->t('ex_vat')
                    );
                }
                if ($coupon->get_free_shipping()) {
                    if (!Number::isZero($amount)) {
                        $description .= ' + ';
                    }
                    $description .= $this->t('free_shipping');
                }
            } else {
                // Value may be entered with or without % sign at the end.
                // Remove it by converting to a float.
                $description .= $coupon->get_amount() . '%';
                if ($coupon->get_free_shipping()) {
                    $description .= ' + ' . $this->t('free_shipping');
                }
            }
        } else {
            // Coupon no longer exists: use generic name.
            $description = $this->t('discount_code');
        }
        return [
            Tag::ItemNumber => $coupon->get_code(),
            Tag::Product => $description,
            Tag::UnitPrice => 0,
            Meta::UnitPriceInc => 0,
            Tag::Quantity => 1,
            Tag::VatRate => null,
            Meta::VatAmount => 0,
            Meta::VatRateSource => VatRateSource::Completor,
            Meta::Id => $couponId,
        ];
    }

    /**
     * Returns whether the prices entered by an admin include taxes or not.
     *
     * @return bool
     *   True if the prices as entered by an admin include VAT, false if they are
     *   entered ex VAT.
     */
    protected function productPricesIncludeTax(): bool
    {
        /** @noinspection PhpUndefinedFunctionInspection   false positive */
        return wc_prices_include_tax();
    }
}
