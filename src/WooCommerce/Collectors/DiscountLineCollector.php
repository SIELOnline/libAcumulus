<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use WC_Coupon;

use function in_array;

/**
 * DiscountLineCollector contains WooCommerce specific {@see LineType::Discount}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class DiscountLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A discount line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectDiscountLine($acumulusObject);
    }

    /**
     * @param \Siel\Acumulus\Data\Line $line
     *   A discount line with the mapped fields filled in.
     */
    protected function collectDiscountLine(Line $line): void
    {
        /**
         * @var \WC_Coupon $coupon
         */
        $coupon = $this->getPropertySource('discountInfo');
        $this->CollectCouponDiscountLine($line, $coupon);
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
     */
    protected function CollectCouponDiscountLine(Line $line, WC_Coupon $coupon): void
    {
        /** @var \Siel\Acumulus\WooCommerce\Invoice\Source $source */
        $source = $this->getPropertySource('source');

        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        $couponId = $coupon->get_id();
        if ($couponId) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), ['fixed_product', 'fixed_cart'])) {
                $amount = $source->getSign() * $coupon->get_amount();
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
        $line->itemNumber = $coupon->get_code();
        $line->product = $description;
        $line->unitPrice = 0;
        $line->metadataSet(Meta::UnitPriceInc, 0);
        $line->quantity = 1;
        $line->vatRate = null;
        $line->metadataSet(Meta::VatAmount, 0);
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        $line->metadataSet(Meta::Id, $couponId);
    }
}
