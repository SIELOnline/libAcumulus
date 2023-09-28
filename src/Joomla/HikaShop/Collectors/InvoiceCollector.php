<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\InvoiceCollector as BaseInvoiceCollector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * InvoiceCollector does foo.
 */
class InvoiceCollector extends BaseInvoiceCollector
{
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $acumulusObject->metadataSet(Meta::PricesIncludeVat, null);
    }
}
