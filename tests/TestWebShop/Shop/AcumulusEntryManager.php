<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;

/**
 * AcumulusEntryManager does foo.
 */
class AcumulusEntryManager extends \Siel\Acumulus\Shop\AcumulusEntryManager
{
    public static array $table = [];
    public static int $incrementId = 1;

    public function getByEntryId(int $entryId): ?AcumulusEntry
    {
        // PHP8.4: array_find()
        foreach (static::$table as $record) {
            if ($record[AcumulusEntry::$keyEntryId] === $entryId) {
                return $this->convertDbResultToAcumulusEntry($record);
            }
        }
        return null;
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        // PHP8.4: array_find()
        foreach (static::$table as $record) {
            if ($record[AcumulusEntry::$keySourceId] === $invoiceSource->getId() && $record[AcumulusEntry::$keySourceType] === $invoiceSource->getType()) {
                return $this->convertDbResultToAcumulusEntry($record, $ignoreLock);
            }
        }
        return null;
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool
    {
        $timestamp = $created->getTimestamp();
        $record = [
            AcumulusEntry::$keyId => static::$incrementId++,
            AcumulusEntry::$keyEntryId => $entryId,
            AcumulusEntry::$keyToken => $token,
            AcumulusEntry::$keySourceId => $invoiceSource->getId(),
            AcumulusEntry::$keySourceType => $invoiceSource->getType(),
            AcumulusEntry::$keyCreated => $timestamp,
            AcumulusEntry::$keyUpdated => $timestamp,
        ];
        static::$table[$record[AcumulusEntry::$keyId]] = $record;
        return true;
    }

    protected function update(AcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool
    {
        $timestamp = $updated->getTimestamp();
        if (isset(static::$table[$entry->getId()])) {
            $record = &static::$table[$entry->getId()];
            $record[AcumulusEntry::$keyEntryId] = $entryId;
            $record[AcumulusEntry::$keyToken] = $token;
            $record[AcumulusEntry::$keyUpdated] = $timestamp;
        }
        return true;
    }

    public function delete(AcumulusEntry $entry): bool
    {
        if (isset(static::$table[$entry->getId()])) {
            unset(static::$table[$entry->getId()]);
        }
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
