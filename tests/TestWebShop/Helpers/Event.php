<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for the test webshop.
 */
class Event implements \Siel\Acumulus\Helpers\Event
{
    /**
     * @inheritDoc
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
    }
}
