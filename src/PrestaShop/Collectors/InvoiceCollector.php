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
        $acumulusObject->metadataSet(Meta::PricesIncludeVat, null);
    }
}
