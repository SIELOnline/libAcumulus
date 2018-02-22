<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Log;

/**
 * Represents acumulus entry records.
 *
 * These records tie orders or credit notes from the web shop to entries in
 * Acumulus.
 *
 * Acumulus identifies entries by their entry id (boekstuknummer in het
 * Nederlands). To access an entry via the API, one must also supply a token
 * that is generated based on the contents of the entry. The entry id and token
 * are stored together with an id for the order or credit note from the web
 * shop.
 *
 * Usages (not (all of them are) yet implemented):
 * - Prevent that an invoice for a given order or credit note is sent twice.
 * - Show additional information on order list screens
 * - Update payment status
 * - Resend Acumulus invoice PDF.
 */
abstract class AcumulusEntryManager
{
    // Access to the fields, differs per webshop as we followed db naming
    // conventions from the webshop.
    static public $keyEntryId = 'entry_id';
    static public $keyToken = 'token';
    static public $keySourceType = 'source_type';
    static public $keySourceId = 'source_id';
    static public $keyCreated = 'created';
    static public $keyUpdated = 'updated';

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * AcumulusEntryManager constructor.
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Returns the Acumulus entry record for the given entry id.
     *
     * @param int|null $entryId
     *   The entry id to look up. If $entryId === null, multiple records may be
     *   found, in which case a numerically indexed array will be returned.
     *
     * @return array|object|null|array[]|object[]
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
     * @return array|object|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    public function getByInvoiceSource($invoiceSource)
    {
        return $this->getByInvoiceSourceId($invoiceSource->getType(), $invoiceSource->getId());
    }

    /**
     * Returns the Acumulus entry record for the given invoice source.
     *
     * @param string $invoiceSourceType
     *   The type of the invoice source
     * @param string $invoiceSourceId
     *   The id of the invoice source for which the invoice was created.
     *
     * @return array|object|null
     *   Acumulus entry record for the given invoice source or null if no
     *   invoice has yet been created in Acumulus for this invoice source.
     */
    abstract public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId);

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
    abstract protected function insert($invoiceSource, $entryId, $token, $created);

    /**
     * Updates the Acumulus entry for the given invoice source.
     *
     * @param array|object $record
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
    abstract protected function update($record, $entryId, $token, $updated);

    /**
     * Returns the value of the given field in the given acumulus entry record.
     *
     * As differnt webshops may use different field and property names in their
     * tables and models, we abstracted accessing a field of a record into this
     * method.
     *
     * @param array|object|null $record
     *   The record to search through.
     * @param string $field
     *   The field to search for.
     *
     * @return mixed|null
     *   The value of the given field in the given acumulus entry record.
     */
    public function getField($record, $field)
    {
        $value = null;
        if (is_array($record)) {
            if (array_key_exists($field, $record)) {
                $value = $record[$field];
            }
        } elseif (is_object($record)) {
            // It's an object: try to get the property.
            // Safest way is via the get_object_vars() function.
            $properties = get_object_vars($record);
            if (!empty($properties) && array_key_exists($field, $properties)) {
                $value = $properties[$field];
            } elseif (method_exists($record, $field)) {
                $value = call_user_func(array($record, $field));
            } elseif (method_exists($record, '__get')) {
                @$value = $record->$field;
            } elseif (method_exists($record, '__call')) {
                @$value = $record->$field();
            }
        }
        return $value;
    }

    /**
     * @return bool
     */
    abstract public function install();

    /**
     * Upgrade the datamodel to the given version. Only called when the module
     * got updated.
     *
     * @param string $version
     *
     * @return bool
     *   Success.
     */
    public function upgrade(/** @noinspection PhpUnusedParameterInspection */ $version)
    {
        return true;
    }

    /**
     * @return bool
     *   Success.
     */
    abstract public function uninstall();
}
