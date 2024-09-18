<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Address;
use Exception;
use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;
use TaxManagerFactory;
use TaxRulesGroup;

/**
 * LineCollector contains PrestaShop common Line collecting logic.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Precision: 1 of the amounts, probably the prince incl tax, is entered by
     * the admin and can thus be considered exact. The other is calculated by
     * the system and not rounded and can thus be considered to have a precision
     * better than 0.0001.
     *
     * However, we have had a support call where the precision, for a credit
     * note, turned out to be only 0.002. This was, apparently, with a price
     * entered excl. vat: 34,22; incl: 41,40378; (computed) vat: 7,18378.
     * The max-vat rate was just below 21%, so no match was made.
     */
    protected float $precision = 0.01;

    /**
     * Looks up and returns vat rate metadata.
     *
     * The following metadata keys might be set:
     * - Meta::VatClassId: int
     * - Meta::VatClassName: string
     * - Meta::VatRateLookup: float
     * - Meta::VatRateLookupLabel: string
     */
    protected function addVatRateLookupMetadata(Line $line, int $addressId, int $taxRulesGroupId): void
    {
        try {
            if (!empty($taxRulesGroupId)) {
                $taxRulesGroup = new TaxRulesGroup($taxRulesGroupId);
                $address = new Address($addressId);
                $taxManager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
                $taxCalculator = $taxManager->getTaxCalculator();
                $line->metadataSet(Meta::VatClassId, $taxRulesGroup->id);
                $line->metadataSet(Meta::VatClassName, $taxRulesGroup->name);
                $line->metadataSet(Meta::VatRateLookup, $taxCalculator->getTotalRate());
                $line->metadataSet(Meta::VatRateLookupLabel, $taxCalculator->getTaxesName());
            } else {
                $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
            }
        } catch (Exception) {
        }
    }
}
