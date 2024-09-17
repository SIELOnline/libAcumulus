<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Magento\Tax\Model\ClassModel as TaxClass;
use Magento\Tax\Model\Config as MagentoTaxConfig;
use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Meta;

/**
 * LineCollector contains common methods for Magento line collectors.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class LineCollector extends BaseLineCollector
{
    use MagentoRegistryTrait;

    /**
     * Adds metadata regarding the tax class to \$Line.
     *
     * The following metadata might be added:
     * - Meta::VatClassId
     * - Meta::VatClassName
     */
    protected function addVatClassMetaData(Line $line, int|string|null $taxClassId): void
    {
        if ($taxClassId) {
            $taxClassId = (int) $taxClassId;
            $line->metadataSet(Meta::VatClassId, $taxClassId);
            /** @var TaxClass $taxClass */
            $taxClass = $this->getRegistry()->create(TaxClass::class);
            $this->getRegistry()->get($taxClass->getResourceName())->load($taxClass, $taxClassId);
            $line->metadataSet(Meta::VatClassName, $taxClass->getClassName());
        } else {
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        }
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   True if the prices for the products are entered with tax, false if the
     *   prices are entered without tax.
     */
    protected function productPricesIncludeTax(): bool
    {
        return $this->getTaxConfig()->priceIncludesTax();
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   true if shipping prices include tax, false otherwise.
     */
    protected function shippingPriceIncludeTax(): bool
    {
        return $this->getTaxConfig()->shippingPriceIncludesTax();
    }

    /**
     * Returns the shipping tax class id.
     *
     * @return int
     *   The id of the tax class used for shipping.
     */
    protected function getShippingTaxClassId(): int
    {
        return $this->getTaxConfig()->getShippingTaxClass();
    }

    /**
     * Returns whether a discount amount includes tax.
     *
     * @return bool
     *   true if a discount is applied on the price including tax, false if a
     *   discount is applied on the price excluding tax.
     */
    protected function discountIncludesTax(): bool
    {
        return $this->getTaxConfig()->discountTax();
    }

    protected function getTaxConfig(): MagentoTaxConfig
    {
        return $this->getRegistry()->create(MagentoTaxConfig::class);
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
