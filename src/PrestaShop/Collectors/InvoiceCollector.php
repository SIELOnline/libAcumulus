<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Collectors\InvoiceCollector as BaseInvoiceCollector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * InvoiceCollector for PrestaShop.
 */
class InvoiceCollector extends BaseInvoiceCollector
{
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        // In PrestaShop you can enter a price with or without vat, the other being
        // automatically updated. So we can not know how prices where entered.
        $acumulusObject->metadataSet(Meta::PricesIncludeVat, null);
    }
}
