<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use DateTimeInterface;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Throwable;
use WHMCS\Database\Capsule;

use function sprintf;

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

    public function getByEntryId(int $entryId): ?AcumulusEntry
    {
        $record = Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keyEntryId, $entryId)
            ->first();

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntry($record);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $record = Capsule::table(static::$tableName)
            ->where(AcumulusEntry::$keySourceType, $invoiceSource->getType())
            ->where(AcumulusEntry::$keySourceId, $invoiceSource->getId())
            ->first();

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntry($record, $ignoreLock);
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool
    {
        $timestamp = $created->format(Api::Format_TimeStamp);
        return Capsule::table(static::$tableName)
            ->insert([
                AcumulusEntry::$keyEntryId => $entryId,
                AcumulusEntry::$keyToken => $token,
                AcumulusEntry::$keySourceType => $invoiceSource->getType(),
                AcumulusEntry::$keySourceId => $invoiceSource->getId(),
                AcumulusEntry::$keyCreated => $timestamp,
                AcumulusEntry::$keyUpdated => $timestamp,
            ]);
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool
    {
        $timestamp = $updated->format(Api::Format_TimeStamp);
        return Capsule::table(static::$tableName)
                ->where(AcumulusEntry::$keyId, $entry->getId())
                ->update([
                    AcumulusEntry::$keyEntryId => $entryId,
                    AcumulusEntry::$keyToken => $token,
                    AcumulusEntry::$keyUpdated => $timestamp,
                ]) > 0;
    }

    public function delete(BaseAcumulusEntry $entry): bool
    {
        return Capsule::table(static::$tableName)
                ->where(AcumulusEntry::$keyId, $entry->getId())
                ->delete() > 0;
    }

    public function install(): bool
    {
        try {
            if (!Capsule::schema()->hasTable(static::$tableName)) {
                Capsule::schema()->create(
                    static::$tableName,
                    function (Blueprint $table) {
                        $table->increments('id');
                        $table->unsignedInteger(AcumulusEntry::$keyEntryId)->nullable(true);
                        $table->char(AcumulusEntry::$keyToken, 32)->nullable(true);
                        $table->string(AcumulusEntry::$keySourceType, 32);
                        $table->unsignedInteger(AcumulusEntry::$keySourceId);
                        // Creates timestamp fields created_at and updated_at.
                        $table->timestamps();
                    }
                );
                $this->copyOldData();
            }
            return true;
        } catch (Throwable $e) {
            $this->log->exception($e);
            return false;
        }
    }

    public function uninstall(): bool
    {
        return true;
    }

    protected function copyOldData(): void
    {
        $oldTableName = 'mod_acumulus_connect';
        if (Capsule::schema()->hasTable($oldTableName)) {
            $query = $this->getInsertIntoQuery($oldTableName);
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
            try {
                $pdo->prepare($query)->execute();
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
            } catch (Exception $e) {
                $this->log->exception($e);
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
    }

    /**
     * Creates the full query string to copy existing data from the former "acumulus
     * connect" addon to the new table.
     */
    protected function getInsertIntoQuery(string $oldTableName): string
    {
        return sprintf(
            'insert into %s (%s, %s, %s, %s, %s, %s) select %s as %s, %s as %s, "%s" as %s, %s as %s, %s as %s, %s as %s from %s order by %s',
            // Target table.
            static::$tableName,
            // Target columns.
            AcumulusEntry::$keyEntryId,
            AcumulusEntry::$keyToken,
            AcumulusEntry::$keySourceType,
            AcumulusEntry::$keySourceId,
            AcumulusEntry::$keyCreated,
            AcumulusEntry::$keyUpdated,
            // Source columns plus their mapping to target column.
            'entryid',
            AcumulusEntry::$keyEntryId,
            'token',
            AcumulusEntry::$keyToken,
            Source::Order, // literal value
            AcumulusEntry::$keySourceType,
            'id',
            AcumulusEntry::$keySourceId,
            'created_at',
            AcumulusEntry::$keyCreated,
            'updated_at',
            AcumulusEntry::$keyUpdated,
            // Target table.
            $oldTableName,
            // Order of inserting
            'created_at'
        );
    }
}
