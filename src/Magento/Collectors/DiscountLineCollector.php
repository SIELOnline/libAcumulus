<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;

/**
 * DiscountLineCollector contains Magento specific {@see LineType::Discount} collecting
 * logic.
 *
 * Discounts in Magento are stored at the order/creditmemo level. If product prices are
 * incl. VAT, the discount amount will also be incl. VAT.
 *
 * @noinspection PhpUnused Instantiated via a factory.
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
     * Collects the part that needs logic from the discount line for the invoice.
     *
     * If product prices are entered incl./ex. VAT, the discount amount is also incl./ex.
     * VAT.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A discount line with the mapped fields already filled in.
     *
     * @throws \Exception
     * @noinspection PhpUnusedParameterInspection
     */
    protected function collectDiscountLine(Line $line, PropertySources $propertySources): void
    {
        if (!$this->productPricesIncludeTax()) {
            // Move amount and precision from unit price inc. to unit price ex.
            $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
            $line->metadataAdd(Meta::PrecisionUnitPrice, $line->metadataGet(Meta::PrecisionUnitPriceInc));
            $line->metadataRemove(Meta::UnitPriceInc);
            $line->metadataRemove(Meta::PrecisionUnitPriceInc);
        }
    }
}
