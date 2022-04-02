<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;

/**
 * Manages {@see AcumulusEntry} records/objects.
 *
 * This manager class performs CRU(D) operations on Acumulus entries in the
 * web shop database. The features of this class include:
 * - Retrieval of an Acumulus entry record for an invoice source (orders or
 *   refunds).
 * - Retrieval of an Acumulus entry record for a given entry id.
 * - Save (insert or update) an Acumulus entry.
 * - Install and uninstall the db table at module install resp. uninstall time.
 */
abstract class AcumulusEntryManager
{
    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Helpers\Container */
    protected $container;

    /**
     * AcumulusEntryManager constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Container $container, Log $log)
    {
        $this->container = $container;
        $this->log = $log;
    }

    /**
     * Returns the Acumulus entry record for the given entry id.
     *
     * @param int|null $entryId
     *   The entry id to look up. If $entryId === null, multiple records may be
     *   found, in which case a numerically indexed array will be returned.
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry|\Siel\Acumulus\Shop\AcumulusEntry[]|null
     *   Acumulus entry record for the given entry id or null if the entry id is
     *   unknown.
     */
    abstract public function getByEntryId(?int $entryId);

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param bool $ignoreLock
     *   Whether to return an entry that serves as a send-lock (false) or ignore
     *   it (true)
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    abstract public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry;

    /**
     * Converts the results of a DB query to AcumulusEntries.
     *
     * @param object|array[]|object[] $result
     *   The DB query result.
     * @param bool $ignoreLock
     *   Whether to return an entry that serves as a send-lock (false) or ignore
     *   it (true).
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry|\Siel\Acumulus\Shop\AcumulusEntry[]|null
     */
    protected function convertDbResultToAcumulusEntries($result, bool $ignoreLock = true)
    {
        if (empty($result)) {
            $result = null;
        } else {
            if (is_object($result)) {
                $result = [$result];
            }
            // Now, it's a non-empty array of results.
            foreach ($result as &$record) {
                $record = $this->container->createAcumulusEntry($record);
                if ($ignoreLock && $record->isSendLock()) {
                    $record = null;
                }
            }
            array_filter($result);
            if (empty($result)) {
                $result = null;
            } elseif (count($result) === 1) {
                $result = reset($result);
            }
        }
        return $result;
    }

    /**
     * Locks an invoice source for sending twice.
     *
     * To prevent two processes or threads to send an invoice twice, the sending
     * process sets a lock on the invoiceSource by already creating an
     * AcumulusEntry for it before starting to send. That record will contain
     * some special values by which it can be recognised as a lock instead of as
     * a reference to a real entry in Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The invoice source to set and acquire a lock on.
     *
     * @return bool
     *   Whether the lock was successfully acquired.
     */
    public function lockForSending(Source $invoiceSource): bool
    {
        return $this->insert($invoiceSource, AcumulusEntry::lockEntryId, AcumulusEntry::lockToken, $this->sqlNow());
    }

    /**
     * Deletes the lock for sending on the given invoice source.
     *
     * @param Source $invoiceSource
     *   The invoice source to delete the lock for.
     *
     * @return int
     *   One of the AcumulusEntry::Lock_... constants describing the status of
     *   the lock.
     */
    public function deleteLock(Source $invoiceSource): int
    {
        $entry = $this->getByInvoiceSource($invoiceSource, false);
        if ($entry === null) {
            // - The process that had the lock may have failed sending the
            //   invoice to Acumulus and has removed the lock (e.g. a connection
            //   timeout, with the timeout being longer than our lock expiry).
            // - Yet another process already cleared the lock.
            return AcumulusEntry::Lock_NoLongerExists;
        }
        if ($entry->isSendLock()) {
            // The lock is still there: remove it.
            $this->delete($entry);
            return AcumulusEntry::Lock_Deleted;
        }
        // The AcumulusEntry became a real entry: apparently the process that
        // had the lock, successfully finished sending the invoice after all.
        return AcumulusEntry::Lock_BecameRealEntry;
    }

    /**
     * Saves the Acumulus entry for the given order in the web shop's database.
     * This default implementation calls getByInvoiceSource() to determine
     * whether to subsequently call insert() or update().
     * So normally, a child class should implement insert() and update() and not
     * override this method.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param int|null $entryId
     *   The Acumulus entry id assigned to the invoice for this order.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     *
     * @return bool
     *   Success.
     */
    public function save(Source $invoiceSource, ?int $entryId, ?string $token): bool
    {
        $now = $this->sqlNow();
        $record = $this->getByInvoiceSource($invoiceSource, false);
        if ($record === null) {
            $result = $this->insert($invoiceSource, $entryId, $token, $now);
        } else {
            $result = $this->update($record, $entryId, $token, $now);
        }
        return $result;
    }

    /**
     * Returns the current time in a format accepted by the actual db layer.
     *
     * @return int|string
     *   Timestamp
     */
    abstract protected function sqlNow();

    /**
     * Inserts an Acumulus entry for the given order in the web shop's database.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param int|null $entryId
     *   The Acumulus entry id assigned to the invoice for this order.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     * @param int|string $created
     *   The creation time (= current time), in the format as the actual
     *   database layer expects for a timestamp.
     *
     * @return bool
     *   Success.
     */
    abstract protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, $created): bool;

    /**
     * Updates the Acumulus entry for the given invoice source.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $entry
     *   The existing record for the invoice source to be updated.
     * @param int|null $entryId
     *   The new Acumulus entry id for the invoice source.
     * @param string|null $token
     *   The new Acumulus token for the invoice source.
     * @param int|string $updated
     *   The update time (= current time), in the format as the actual database
     *   layer expects for a timestamp.
     *
     * @return bool
     *   Success.
     */
    abstract protected function update(AcumulusEntry $entry, ?int $entryId, ?string $token, $updated): bool;

    /**
     * Deletes the Acumulus entry for the given entry id.
     *
     * @param int $entryId
     *   The Acumulus entry id to delete.
     *
     * @return bool
     *   Success.
     *
     * @noinspection PhpUnused
     */
    public function deleteByEntryId(int $entryId): bool
    {
        if ($entryId >= 2) {
            $entry = $this->getByEntryId($entryId);
            if ($entry instanceof AcumulusEntry) {
                return $this->delete($entry);
            }
        }
        return true;
    }

    /**
     * Deletes the given AcumulusEntry.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $entry
     *   The Acumulus entry to delete.
     *
     * @return bool
     *   Success.
     */
    abstract public function delete(AcumulusEntry $entry): bool;

    /**
     * Installs the data model. Called when the module gets installed.
     *
     * @return bool
     *   Success.
     */
    abstract public function install(): bool;

    /**
     * Upgrades the data model. Called when the module gets updated.
     *
     * @param string $currentVersion
     *   The current version we are updating from.
     *
     * @return bool
     *   Success.
     */
    public function upgrade(string $currentVersion): bool
    {
        return true;
    }

    /**
     * Uninstalls the data model. Called when the module gets uninstalled.
     *
     * @return bool
     *   Success.
     */
    abstract public function uninstall(): bool;
}
