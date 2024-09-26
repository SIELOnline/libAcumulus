<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;

/**
 * DiscountLineCollector contains PrestaShop specific {@see LineType::Discount} collecting
 * logic.
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
     * Collects a discount line for 1 order total line of type 'discount'.
     *
     * @param Line $line
     *   A discount line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function collectDiscountLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $propertySources->get('source');

        /** @var array $calcRule A record from the order_cart_rule table. */
        $calcRule = $propertySources->get('discountLineInfo');

        // Amounts in cart rules are always positive, so negate the sign.
        $sign = $source->getSign();
        $discountInc = -$sign * $calcRule['value'];
        $discountEx = -$sign * $calcRule['value_tax_excl'];
        $discountVat = $discountInc - $discountEx;
        $line->itemNumber = $calcRule['id_cart_rule'];
        $line->product = $this->t('discount_code') . ' ' . $calcRule['name'];
        $line->unitPrice = $discountEx;
        $line->metadataSet(Meta::UnitPriceInc, $discountInc);
        $line->quantity = 1;
        // If no match is found, this line may be split.
        $line->metadataSet(Meta::StrategySplit, true);
        // Assuming that the fixed discount amount was entered:
        // - including VAT, the precision would be 0.01, 0.01.
        // - excluding VAT, the precision would be 0.01, 0
        // However, for a %, it will be: 0.02, 0.01, so use 0.02.
        self::addVatRangeTags($line, $discountVat, $discountEx, 0.02, 0.01);
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
    }
}
