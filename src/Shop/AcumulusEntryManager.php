<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTimeImmutable;
use DateTimeInterface;
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
 * - Install and uninstall the db table at module install respectively uninstall time.
 */
abstract class AcumulusEntryManager
{
    protected Log $log;
    protected Container $container;

    public function __construct(Container $container, Log $log)
    {
        $this->container = $container;
        $this->log = $log;
    }

    /**
     * Returns the Acumulus entry record for the given entry id.
     *
     * @param int $entryId
     *   The entry id to look up.
     *
     * @return AcumulusEntry|null
     *   Acumulus entry record for the given entry id or null if the entry id is
     *   unknown.
     */
    abstract public function getByEntryId(int $entryId): ?AcumulusEntry;

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param bool $ignoreLock
     *   Whether also to return the entry if it serves as a send-lock (false),
     *   or to ignore it (true, default).
     *
     * @return AcumulusEntry|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    abstract public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry;

    /**
     * Converts the (single) result of a DB query to an {@see AcumulusEntry}.
     *
     * @param object|array|null $result
     *   The DB query result. An empty array as result is seen as no result (OpenCart).
     * @param bool $ignoreLock
     *   Whether to return an entry that serves as send-lock (false) or ignore it (true).
     */
    protected function convertDbResultToAcumulusEntry(object|array|null $result, bool $ignoreLock = true): ?AcumulusEntry
    {
        if (empty($result)) {
            $acumulusEntry = null;
        } else {
            $acumulusEntry = $this->container->createAcumulusEntry($result);
            if ($ignoreLock && $acumulusEntry->isSendLock()) {
                $acumulusEntry = null;
            }
        }
        return $acumulusEntry;
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
        return $this->insert($invoiceSource, AcumulusEntry::lockEntryId, AcumulusEntry::lockToken, new DateTimeImmutable());
    }

    /**
     * Deletes the lock for sending on the given invoice source.
     *
     * @param Source $invoiceSource
     *   The invoice-source to delete the lock for.
     *
     * @return int
     *   One of the AcumulusEntry::Lock_... constants describing the status of
     *   the lock:
     *   - AcumulusEntry::Lock_Deleted: success
     *   - AcumulusEntry::Lock_BecameRealEntry: (probably) the process that held
     *     the lock was successful after all
     *   - AcumulusEntry::Lock_NoLongerExists: another process deleted it or the
     *     process that created the lock finished unsuccessfully after all.
     */
    public function deleteLock(Source $invoiceSource): int
    {
        $entry = $this->getByInvoiceSource($invoiceSource, false);
        if ($entry === null) {
            // - The process that had the lock may have failed to send the
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
        // The AcumulusEntry became a real entry: apparently the process which
        // had the lock successfully finished sending the invoice after all.
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
     * @param int|string|null $entryId
     *   The Acumulus entry id assigned to the invoice for this order. This is
     *   an int but is returned as a string by the API.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     *
     * @return bool
     *   Success.
     */
    public function save(Source $invoiceSource, int|string|null $entryId, ?string $token): bool
    {
        if ($entryId !== null) {
            $entryId = (int) $entryId;
        }
        $record = $this->getByInvoiceSource($invoiceSource, false);
        if ($record === null) {
            $result = $this->insert($invoiceSource, $entryId, $token, new DateTimeImmutable());
        } else {
            $result = $this->update($record, $entryId, $token, new DateTimeImmutable());
        }
        return $result;
    }

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
     * @param DateTimeInterface $created
     *   The creation time (null = current time).
     *
     * @return bool
     *   Success.
     */
    abstract protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool;

    /**
     * Updates the Acumulus entry for the given invoice source.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $entry
     *   The existing record for the invoice source to be updated.
     * @param int|null $entryId
     *   The new Acumulus entry id for the invoice source.
     * @param string|null $token
     *   The new Acumulus token for the invoice source.
     * @param DateTimeInterface $updated
     *   The update time (null = current time).
     *
     * @return bool
     *   Success.
     */
    abstract protected function update(AcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool;

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
     *   The source object for which to delete the {@see \Siel\Acumulus\Shop\AcumulusEntry}.
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
     * Override this method if your webshop does not provide its own upgrade mechanisms in
     * specific locations, like, e.g, PrestaShop's install-x.y.z.php files in the
     * /modules/<module_name>/upgrade folder.
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
     *
     * @todo: should we delete any data and table created? If so, should we pass a bool
     *   parameter to guide that?
     */
    abstract public function uninstall(): bool;
}
