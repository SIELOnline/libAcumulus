<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * DiscountLineCollector contains VirtueMart specific {@see LineType::Discount} collecting
 * logic.
 *
 * We do have several discount related fields in the order details:
 * - order_billDiscountAmount
 * - order_discountAmount
 * - coupon_discount
 * - order_discount
 * However, these fields seem to be totals based on applied non-tax
 * calculation rules. So it is better to add a line per calc rule with a
 * negative amount: this gives us descriptions of the discounts as well.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class DiscountLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectDiscountLine($acumulusObject, $propertySources);
    }

    /**
     * Collects a discount line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectDiscountLine(Line $line, PropertySources $propertySources): void
    {
        /**
         * @var Source|object $discountInfo
         *   Either a source (coupon) or a calc_rule record in a stdClass.
         */
        $discountInfo = $propertySources->get('discountLineInfo');

        // Coupon codes are not stored in calc rules, so handle them separately.
        if ($discountInfo instanceof Source) {
            $this->collectCouponCodeDiscountLine($line, $discountInfo);
        } else {
            $this->getCalcRuleDiscountLine($line, $discountInfo);
        }
    }

    /**
     * Returns a discount line for the coupon code discount on this order.
     *
     * Only the total discount amount is stored, so we have to divide this amount over the
     * vat rates that appear in this order, possibly splitting this line into multiple
     * lines in the strategy phase.
     */
    protected function collectCouponCodeDiscountLine(Line $line, Source $source): void
    {
        $line->itemNumber = $source->getShopObject()['details']['BT']->coupon_code;
        $line->product = $this->t('discount');
        $line->quantity = 1;
        $line->metadataSet(Meta::UnitPriceInc, (float) $source->getShopObject()['details']['BT']->coupon_discount);
        $line->vatRate = null;
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
        $line->metadataSet(Meta::StrategySplit, true);
    }

    /**
     * Returns a discount item line for the given discount calculation rule.
     *
     * The returned line will only contain a discount amount including tax.
     * The completor/strategy phase will have to divide this amount over vat rates that
     * are used in this invoice.
     */
    protected function getCalcRuleDiscountLine(Line $line, object $calcRule): void
    {
        $line->product = $calcRule->calc_rule_name;
        $line->quantity = 1;
        $line->metadataSet(Meta::UnitPriceInc, (float) $calcRule->calc_amount);
        $line->vatRate = null;
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
        $line->metadataSet(Meta::StrategySplit, true);
    }
}

