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
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use WC_Coupon;
use WC_Order_Item_Fee;
use WC_Tax;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

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
     * Looks up and returns vat rate metadata for product lines.
     * A product has a tax class. A tax class can have multiple tax rates,
     * depending on the region of the customer. We use the customers address in
     * the raw invoice that is being created, to get the possible rates.
     *
     * @param string|null $taxClassId
     *   The tax class of the product. For the default tax class it can be
     *   'standard' or the empty string. For no tax class at all, it will be
     *   PluginConfig::VatClass_Null.
     * @todo: Can it be null?
     *
     * @return array
     *   An array with keys:
     *   - Meta::VatClassId: string
     *   - Meta::VatRateLookup: float[]
     *   - Meta::VatRateLookupLabel: string[]
     */
    protected function getVatRateLookupMetadataByTaxClass(?string $taxClassId): array
    {
        if ($taxClassId === null) {
            $result = [
                Meta::VatClassId => Config::VatClass_Null,
            ];
        } else {
            // '' denotes the 'standard' tax class, use 'standard' in metadata,
            // '' when searching.
            if ($taxClassId === '') {
                $taxClassId = 'standard';
            }
            $result = [
                Meta::VatClassId => sanitize_title($taxClassId),
                // Vat class name is the non-sanitized version of the id
                // and thus does not convey more information: don't add.
                Meta::VatRateLookup => [],
                Meta::VatRateLookupLabel => [],
            ];
            if ($taxClassId === 'standard') {
                $taxClassId = '';
            }

            // Find applicable vat rates. We use WC_Tax::find_rates() to find
            // them.
            $args = [
                'tax_class' => $taxClassId,
                'country' => $this->invoice[Tag::Customer][Tag::CountryCode],
                'city' => $this->invoice[Tag::Customer][Tag::City] ?? '',
                'postcode' => $this->invoice[Tag::Customer][Tag::PostalCode] ?? '',
            ];
            $taxRates = WC_Tax::find_rates($args);
            foreach ($taxRates as $taxRate) {
                $result[Meta::VatRateLookup][] = $taxRate['rate'];
                $result[Meta::VatRateLookupLabel][] = $taxRate['label'];
            }
        }
        return $result;
    }

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

    protected function getShippingLines(): array
    {
        $result = [];
        // Get the shipping lines for this order.
        /** @var \WC_Order_Item_Shipping[] $shippingItems */
        $shippingItems = $this->invoiceSource->getSource()->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        foreach ($shippingItems as $shippingItem) {
            $shippingLine = $this->getShippingLine($shippingItem);
            if ($shippingLine) {
                $result[] = $shippingLine;
            }
        }
        return $result;
    }

    protected function getShippingLine(): array
    {
        /** @var \WC_Order_Item_Shipping $item */
        $item = func_get_arg(0);
        $taxes = $item->get_taxes();
        $vatLookupTags = $this->getShippingVatRateLookupMetadata($taxes);

        // Note: this info is WC3+ specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        $shippingEx = (float) $item->get_total();
        $precisionShippingEx = 0.01;

        // To avoid rounding errors, we try to get the non-formatted amount.
        // Due to changes in how WC configures shipping methods (now based on
        // zones), storage of order item metadata has changed. Therefore, we
        // have to try several option names.
        $methodId = $item->get_method_id();
        if (str_starts_with($methodId, 'legacy_')) {
            $methodId = substr($methodId, strlen('legacy_'));
        }
        // Instance id is the zone, will return an empty value if not present.
        $instanceId = $item->get_instance_id();
        $optionName = !empty($instanceId)
            ? "woocommerce_{$methodId}_{$instanceId}_settings"
            : "woocommerce_{$methodId}_settings";
        $option = get_option($optionName);

        if (!empty($option['cost'])) {
        // Note that "Cost" may contain a formula or use commas: 'Vul een bedrag(excl.
        // btw) in of een berekening zoals 10.00 * [qty]. Gebruik [qty] voor het
        // aantal artikelen, [cost] voor de totale prijs van alle artikelen, en
        // [fee percent="10" min_fee="20" max_fee=""] voor prijzen gebaseerd op
        // percentage.'
            $cost = str_replace(',', '.', $option['cost']);
            if (is_numeric($cost)) {
                $cost = (float) $cost;
                if (Number::floatsAreEqual($cost, $shippingEx)) {
                    $shippingEx = $cost;
                    $precisionShippingEx = 0.001;
                }
            }
        }
        $quantity = $item->get_quantity();
        $shippingEx /= $quantity;
        $shippingVat = $item->get_total_tax() / $quantity;
        $precisionVat = 0.01;

        return [
                Tag::Product => $item->get_name(),
                Tag::UnitPrice => $shippingEx,
                Tag::Quantity => $quantity,
                Meta::Id => $item->get_id(),
            ]
            + self::getVatRangeTags($shippingVat, $shippingEx, $precisionVat, $precisionShippingEx)
            + $vatLookupTags;
    }

    /**
     * Looks up and returns vat rate metadata for shipping lines.
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if
     * that is possible for Dutch web shops, but if a shipping line does have
     * multiple taxes we fall back to the tax class setting for shipping
     * methods, that can have multiple tax rates itself (@param array|array[]|null $taxes
     *   The taxes applied to a shipping line.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatRateLookup (*)
     *   - Meta::VatRateLookupLabel (*)
     *   - Meta::VatRateLookupSource (*)
     * @see
     * getVatRateLookupMetadataByTaxClass()). Anyway, this method will only
     * return metadata if only 1 rate was found.
     */
    protected function getShippingVatRateLookupMetadata(?array $taxes): array
    {
        $result = [];
        if (is_array($taxes)) {
            // Since version ?.?, $taxes has an indirection by key 'total'.
            if (is_string(key($taxes))) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $taxes = current($taxes);
            }
            /** @noinspection NotOptimalIfConditionsInspection */
            if (is_array($taxes)) {
                foreach ($taxes as $taxRateId => $amount) {
                    if (!empty($amount) && !Number::isZero($amount)) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId, OBJECT);
                        if ($taxRate) {
                            if (count($result) === 0) {
                                $result = [
                                    Meta::VatClassId => $taxRate->tax_rate_class !== '' ? $taxRate->tax_rate_class : 'standard',
                                    // Vat class name is the non-sanitized
                                    // version of the id and thus does not
                                    // convey more information: don't add.
                                    Meta::VatRateLookup => [],
                                    Meta::VatRateLookupLabel => [],
                                    Meta::VatRateLookupSource => 'shipping line taxes',
                                ];
                            }
                            // get_rate_percent() contains a % at the end of the
                            // string: remove it.
                            $result[Meta::VatRateLookup][] = substr(WC_Tax::get_rate_percent($taxRateId), 0, -1);
                            $result[Meta::VatRateLookupLabel][] = WC_Tax::get_rate_label($taxRate);
                        }
                    }
                }
            }
        }

        if (count($result) === 0) {
            // Apparently we have free shipping (or a misconfigured shipment
            // method). Use a fall-back: WooCommerce only knows 1 tax rate
            // for all shipping methods, stored in config:
            $shippingTaxClass = get_option('woocommerce_shipping_tax_class');
            if (is_string($shippingTaxClass)) {
                /** @var \WC_Order $order */
                $order = $this->invoiceSource->getOrder()->getSource();

                // Since WC3, the shipping tax class can be based on those from
                // the product items in the cart (which should be the preferred
                // value for this setting). The code to get the derived tax
                // class is more or less copied from WC_Abstract_Order.
                if ($shippingTaxClass === 'inherit') {
                    $foundClasses = array_intersect(array_merge([''], WC_Tax::get_tax_class_slugs()), $order->get_items_tax_classes());
                    $shippingTaxClass = count($foundClasses) === 1 ? reset($foundClasses) : false;
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_string($shippingTaxClass)) {
                    $result = $this->getVatRateLookupMetadataByTaxClass($shippingTaxClass);
                    if (count($result) > 0) {
                        $result[Meta::VatRateLookupSource] = "get_option('woocommerce_shipping_tax_class')";
                    }
                }
            }
        }

        return $result;
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
