<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Shop;

use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * OC4 specific code for an invoiceManager.
 */
class InvoiceManager extends BaseInvoiceManager
{
    protected function getLocation(): string
    {
        return $this->getRegistry()->getExtensionRoute();
    }
}
