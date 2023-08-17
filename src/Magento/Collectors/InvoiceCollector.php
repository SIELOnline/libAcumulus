<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Collectors\InvoiceCollector as BaseInvoiceCollector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * InvoiceCollector does foo.
 */
class InvoiceCollector extends BaseInvoiceCollector
{
    use MagentoRegistryTrait;

    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $acumulusObject->metadataSet(Meta::PricesIncludeVat, $this->productPricesIncludeVat());
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   True if the prices for the products are entered with tax, false if the
     *   prices are entered without tax.
     */
    protected function productPricesIncludeVat(): bool
    {
        return $this->getTaxConfig()->priceIncludesTax();
    }
}
