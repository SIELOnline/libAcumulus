<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use Illuminate\Database\Schema\Blueprint;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Throwable;
use WHMCS\Database\Capsule;

/**
 * Implements the WHMCS specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * WHMCS uses the Laravel DB library which takes care of sanitising and escaping.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{

    private static string $tableName = 'mod_acumulus_entries';

    public function getByEntryId(?int $entryId): ?AcumulusEntry
    {
        $record = Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keyEntryId, $entryId)
            ->first();

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntries($record);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $record = Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keySourceType, $invoiceSource->getType())
            ->where(AcumulusEntry::$keySourceId, $invoiceSource->getId())
            ->first();

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntries($record, $ignoreLock);
    }

    protected function sqlNow(): int|string
    {
        // @todo: test that the time component is returned and is correct.
        return toMySQLDate(getTodaysDate());
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        return Capsule::table(static::$tableName)
            ->insert([
                AcumulusEntry::$keyEntryId => $entryId,
                AcumulusEntry::$keyToken => $token,
                AcumulusEntry::$keySourceType => $invoiceSource->getType(),
                AcumulusEntry::$keySourceId => $invoiceSource->getId(),
                AcumulusEntry::$keyCreated => $created,
                AcumulusEntry::$keyUpdated => $created,
            ]);
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        return Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keyId, $entry->getId())
            ->update([
                AcumulusEntry::$keyEntryId => $entryId,
                AcumulusEntry::$keyToken => $token,
                AcumulusEntry::$keyUpdated => $updated,
            ]) > 0;
    }

    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        return Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keyId, $entry->getId())
            ->delete() > 0;
    }

    public function install(): bool
    {
        try {
            Capsule::schema()->create(
                static::$tableName,
                function (Blueprint $table) {
                    $table->increments('id');
                    $table->unsignedInteger('entryid');
                    $table->char('token', 32);
                    $table->string('sourcetype', 32);
                    $table->unsignedInteger('sourceid');
                    $table->timestamps();
                }
            );
            return true;
        } catch (Throwable $e) {
            acumulus_logException($e);
            return false;
        }
    }

    public function uninstall(): bool
    {
        return true;
    }
}
