<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;

/**
 * Manages {@see AcumulusEntry} records/objects.
 *
 * This manager class performs CRU(D) operations on Acumulus entries in the
 * webshop database. The features of this class include:
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
    abstract public function getByEntryId($entryId);

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    abstract public function getByInvoiceSource(Source $invoiceSource);

    /**
     * Converts the results of a DB query to AcumulusEntries.
     *
     * @param object|array[]|object[] $result
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntry|\Siel\Acumulus\Shop\AcumulusEntry[]|null
     */
    protected function convertDbResultToAcumulusEntries($result)
    {
        if (empty($result)) {
            $result = null;
        } elseif (is_object($result)) {
            $result = $this->container->getAcumulusEntry($result);
        } else {
            // It's an array of results
            if (count($result) === 0) {
                $result = null;
            } else {
                foreach ($result as &$record) {
                    $record = $this->container->getAcumulusEntry($record);
                }
                if (count($result) === 1) {
                    $result = reset($result);
                }
            }
        }
        return $result;
    }

    /**
     * Saves the Acumulus entry for the given order in the web shop's database.
     *
     * This default implementation calls getByInvoiceSource() to determine
     * whether to subsequently call insert() or update().
     *
     * So normally, a child class should implement insert() and update() and not
     * override this method.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object for which the invoice was created.
     * @param int|null $entryId
     *   The Acumulus entry Id assigned to the invoice for this order.
     * @param string|null $token
     *   The Acumulus token to be used to access the invoice for this order via
     *   the Acumulus API.
     *
     * @return bool
     *   Success.
     */
    public function save($invoiceSource, $entryId, $token)
    {
        $now = $this->sqlNow();
        $record = $this->getByInvoiceSource($invoiceSource);
        if ($record === null) {
            return $this->insert($invoiceSource, $entryId, $token, $now);
        } else {
            return $this->update($record, $entryId, $token, $now);
        }
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
     *   The Acumulus entry Id assigned to the invoice for this order.
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
    abstract protected function insert(Source $invoiceSource, $entryId, $token, $created);

    /**
     * Updates the Acumulus entry for the given invoice source.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $record
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
    abstract protected function update(AcumulusEntry $record, $entryId, $token, $updated);

    /**
     * Installs the datamodel. Called when the module gets installed.
     *
     * @return bool
     *   Success.
     */
    abstract public function install();

    /**
     * Upgrades the datamodel. Called when the module gets updated.
     *
     * @param string $version
     *   The version to update to.
     *
     * @return bool
     *   Success.
     */
    public function upgrade(/** @noinspection PhpUnusedParameterInspection */ $version)
    {
        return true;
    }

    /**
     * Uninstalls the datamodel. Called when the module gets uninstalled.
     *
     * @return bool
     *   Success.
     */
    abstract public function uninstall();
}
