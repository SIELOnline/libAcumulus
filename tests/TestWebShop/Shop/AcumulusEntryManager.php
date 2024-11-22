<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;

/**
 * AcumulusEntryManager does foo.
 */
class AcumulusEntryManager extends \Siel\Acumulus\Shop\AcumulusEntryManager
{

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        return null;
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        return null;
    }

    protected function sqlNow(): int
    {
        return time();
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        return true;
    }

    protected function update(AcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        return true;
    }

    public function delete(AcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        return true;
    }

    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        return true;
    }
}
