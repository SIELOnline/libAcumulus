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
 * ItemLineCollector contains PrestaShop specific {@see LineType::Item} collecting logic.
 */
class LineCollector extends BaseLineCollector
{
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
