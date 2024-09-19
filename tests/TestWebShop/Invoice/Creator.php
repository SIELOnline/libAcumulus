<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Invoice\Source;

/**
 * Creator contains legacy Creator functions for TestWebShop.
 */
class Creator extends \Siel\Acumulus\Invoice\Creator
{
    public function create(Source $source, Invoice $invoice): void
    {
        // For now, we do not implement Legacy features in the test web shop.
    }
}
