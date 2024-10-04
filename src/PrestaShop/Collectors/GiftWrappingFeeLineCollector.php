<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Configuration;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * GiftWrappingFeeLineCollector contains PrestaShop specific {@see LineType::PaymentFee}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class GiftWrappingFeeLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A gift wrapping fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectGiftWrappingFeeLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the gift wrapping fee line for the invoice.
     *
     * total_wrapping_tax_excl is not very precise (rounded to the cent) and can easily
     * lead to 1 cent off invoices in Acumulus (assuming that the amount entered is based
     * on a nicely rounded amount incl tax). So we recalculate this ourselves by looking
     * up the tax rate.
     *
     * Credit slips cannot have a gift wrapping line.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A gift wrapping fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectGiftWrappingFeeLine(Line $line, PropertySources $propertySources): void
    {
        /** @var Source $source */
        $source = $propertySources->get('source');

        $wrappingEx = $source->getShopObject()->total_wrapping_tax_excl;
        $wrappingExLookedUp = (float) Configuration::get('PS_GIFT_WRAPPING_PRICE');
        // Increase precision if possible.
        if (Number::floatsAreEqual($wrappingEx, $wrappingExLookedUp, 0.005)) {
            $wrappingEx = $wrappingExLookedUp;
            $line->metadataAdd(Meta::FieldsCalculated, Tag::UnitPrice);
            $precision = $this->precision;
        } else {
            $precision = 0.01;
        }
        $wrappingInc = $source->getShopObject()->total_wrapping_tax_incl;
        $wrappingVat = $wrappingInc - $wrappingEx;
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);

        $this->addVatRateLookupMetadata(
            $line,
            $source->getShopObject()->id_address_invoice,
            (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP')
        );
        $line->product = $this->t('gift_wrapping');
        $line->quantity = 1;
        $line->unitPrice = $wrappingEx;
        $line->metadataSet(Meta::VatAmount, $wrappingVat);
        $line->metadataSet(Meta::UnitPriceInc, $wrappingInc);
        $line->metadataSet(Meta::PrecisionUnitPrice, $precision);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01 + $precision);
    }
}

