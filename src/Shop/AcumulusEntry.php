<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\PluginConfig;

/**
 * Ties webshop orders or credit notes to entries in Acumulus.
 *
 * Acumulus identifies entries by their entry id (boekstuknummer in het
 * Nederlands) or, for a number of API calls, a token. Both the entry id and
 * token are stored together with information that identifies the shop invoice
 * source (order or credit note) and create and last updated timestamps. Most
 * webshops also require/expect a single primary key (technical key) but that
 * is irrelevant for this class.
 *
 * Usages of this information (* = not (yet) implemented):
 * - Prevent that an invoice for a given order or credit note is sent twice.
 * - Show additional information on order or order list screens (*).
 * - Update payment status (*)
 * - Resend Acumulus invoice PDF (*).
 * At the moment of writing (april 2018), the 3 not yet implemented features are
 * being added to the Acumulus WooCommerce plugin.
 */
class AcumulusEntry
{
    // Access to the fields, differs per webshop as we follow db naming
    // conventions from the webshop.
    static protected $keyEntryId = 'entry_id';
    static protected $keyToken = 'token';
    static protected $keySourceType = 'source_type';
    static protected $keySourceId = 'source_id';
    static protected $keyCreated = 'created';
    static protected $keyUpdated = 'updated';

    // The format of the created and updated timestamps, when saved as a string.
    static protected $timestampFormat = PluginConfig::TimeStampFormat_Sql;

    // Constants to enable some kind of locking and thereby preventing sending
    // invoices twice.
    static protected $maxLockTime = 40;
    const lockEntryId = 1;
    const lockToken = 'Send locked, delete if too old';

    // Constants that define the various delete lock results.
    const Lock_NoLongerExists = 1;
    const Lock_Deleted = 2;
    const Lock_BecameRealEntry = 3;

    /**
     * @var array|object
     *
     * The webshop specific data holder for the Acumulus entry.
     */
    protected $record;

    /**
     * AcumulusEntryManager constructor.
     *
     * @param array|object $record
     *   A webshop specific record object or array that holds an Acumulus entry
     *   record.
     */
    public function __construct($record)
    {
        $this->record = $record;
    }

    /**
     * Returns the entry id for this Acumulus entry.
     *
     * @return int|null
     *   The entry id of this Acumulus entry or null if it was stored as a
     *   concept.
     */
    public function getEntryId()
    {
        $entryId = $this->get(static::$keyEntryId);
        if (!empty($entryId)) {
            $entryId = (int) $entryId;
        }
        return $entryId;
    }

    /**
     * Returns the entry id for this Acumulus entry.
     *
     * @return string|null
     *   The token for this Acumulus entry or null if it was stored as a
     *   concept.
     */
    public function getToken()
    {
        return $this->get(static::$keyToken);
    }

    /**
     * Return the type of shop source this Acumulus entry was created for.
     *
     * @return string
     *   The type of shop source being Source::Order or Source::CreditNote.
     */
    public function getSourceType()
    {
        return $this->get(static::$keySourceType);
    }

    /**
     * Returns the id of the shop source this Acumulus entry was created for.
     *
     * @return int
     *   The id of the shop source.
     */
    public function getSourceId()
    {
        return $this->get(static::$keySourceId);
    }

    /**
     * Returns the time when this record was created.
     *
     * @param bool $raw
     *   Whether to return the raw value as stored in the database, or a
     *   Datetime object. The raw value will differ per webshop.
     *
     * @return string|int|\DateTime
     *   The timestamp when this record was created.
     */
    public function getCreated($raw = false)
    {
        $result = $this->get(static::$keyCreated);
        if (!$raw) {
            $result = $this->toDateTime($result);
        }
        return $result;
    }

    /**
     * Returns the time when this record was last updated.
     *
     * @param bool $raw
     *   Whether to return the raw value as stored in the database, or a
     *   Datetime object. The raw value will differ per webshop.
     *
     * @return string|int|\DateTime
     *   The timestamp when this record was last updated.
     */
    public function getUpdated($raw = false)
    {
        $result = $this->get(static::$keyUpdated);
        if (!$raw) {
            $result = $this->toDateTime($result);
        }
        return $result;
    }

    /** @noinspection PhpDocMissingThrowsInspection
     *
     * Returns a DateTime object based on the timestamp in database format.
     *
     * @param int|string $timestamp
     *
     * @return bool|\DateTime
     */
    protected function toDateTime($timestamp)
    {
        if (is_numeric($timestamp)) {
            // Unix timestamp.
            $result = new DateTime();
            $result->setTimestamp($timestamp);
        } else {
            // Formatted timestamp, e.g. yyyy-mm-dd hh:mm:ss.
            $result = DateTime::createFromFormat(static::$timestampFormat, $timestamp);
        }
        return $result;
    }

    /**
     * Returns the shop specific record for this Acumulus entry.
     *
     * This getter should only be used by the AcumulusEntryManager.
     *
     * @return array|object
     *   The shop specific record for this Acumulus entry.
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * Returns the value of the given field in the given acumulus entry record.
     *
     * As different webshops may use different field and property names in their
     * tables and models, we abstracted accessing a field of a record into this
     * method.
     *
     * @param string $field
     *   The field to search for.
     *
     * @return mixed|null
     *   The value of the given field in this acumulus entry record.
     */
    protected function get($field)
    {
        $value = null;
        if (is_array($this->record)) {
            // Value may be null: use array_key_exists(), not isset().
            if (array_key_exists($field, $this->record)) {
                $value = $this->record[$field];
            }
        } elseif (is_object($this->record)) {
            // It's an object: try to get the property.
            // Safest way is via the get_object_vars() function.
            $properties = get_object_vars($this->record);
            if (!empty($properties) && array_key_exists($field, $properties)) {
                $value = $properties[$field];
            } elseif (method_exists($this->record, $field)) {
                $value = call_user_func(array($this->record, $field));
            } elseif (method_exists($this->record, '__get')) {
                @$value = $this->record->$field;
            } elseif (method_exists($this->record, '__call')) {
                @$value = $this->record->$field();
            }
        }
        return $value;
    }

    /**
     * Returns whether the entry serves as a lock on sending.
     *
     * This method just indicates if there is a "lock" on the entry, even if
     * that lock already has expired. So normally you also want to check
     * hasLockExpired().
     *
     * @return bool
     *   True if the entry serves as a lock on sending instead of as a reference
     *   to the invoice in Acumulus, false otherwise.
     */
    public function isSendLock()
    {
        return $this->getEntryId() === static::lockEntryId && $this->getToken() === static::lockToken;
    }

    /** @noinspection PhpDocMissingThrowsInspection
     *
     * Returns whether there is a lock on sending the invoice, but has expired.
     *
     * @return bool
     *   True if the entry indicates that there is a lock on sending the
     *   invoice, but has expired, false otherwise.
     */
    public function hasLockExpired()
    {
        return $this->isSendLock() && time() - $this->getCreated()->getTimestamp() > static::$maxLockTime;
    }
}
