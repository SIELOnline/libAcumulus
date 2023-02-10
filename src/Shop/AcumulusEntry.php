<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Api;

use function array_key_exists;
use function call_user_func;
use function is_array;
use function is_object;

/**
 * An AcumulusEntry links a webshop order or credit note to an entry in
 * Acumulus.
 *
 * Acumulus identifies entries by their entry id (Dutch: boekstuknummer) or,
 * for a number of API calls, a token. Both the entry id and token are stored
 * together with information that identifies the shop invoice source (order or
 * credit note) and create and last updated timestamps.
 *
 * Concepts do not have a token, they only have a concept id. As this feature
 * was added after this class and the underlying storage had been created, we
 * store the concept id in the entry id field and use the token field (empty or
 * set) to determine what the entry id field refers to.
 *
 * A 3rd "type" of entry is a "lock" entry that is inserted just before an
 * invoiceAdd request is sent and deleted/replaced with a real record after the
 * result has been received. This is used to prevent sending an invoice twice at
 * more or less the same time.
 *
 * Summarising, each entry characterises one of the following:
 * - Entry: entryId = entry id AND token = token
 * - Concept old style: entryId = null AND token = null
 * - Concept new style: entryId = concept id AND token = null
 * - Lock record: entryId = const lockEntryId AND token = const lockToken
 *
 * Most web shops also require/expect a single primary key (technical key) but
 * that is irrelevant for this class.
 *
 * Usages of this information (* = not (yet) implemented):
 * - Prevent that an invoice for a given order or credit note is sent twice.
 * - Show additional information on order or order list screens.
 * - Update payment status.
 * - Show or resend(*) Acumulus invoice PDF.
 *
 * Note: some of these features are only implemented in the Acumulus
 *   WooCommerce plugin.
 */
class AcumulusEntry
{
    /**
     * Constants to enable some kind of locking, thereby preventing sending
     * invoices twice.
     */
    public const lockEntryId = 1;
    public const lockToken = 'Send locked, delete if too old';
    public const conceptIdUnknown = 0;
    /**
     * Constants that define the various delete lock results.
     */
    public const Lock_NoLongerExists = 1;
    public const Lock_Deleted = 2;
    public const Lock_BecameRealEntry = 3;


    /**
     * Access to the fields, may differ per web shop as we follow db naming
     * conventions from the web shop.
     */
    protected static string $keyEntryId = 'entry_id';
    protected static string $keyToken = 'token';
    protected static string $keySourceType = 'source_type';
    protected static string $keySourceId = 'source_id';
    protected static string $keyCreated = 'created';
    protected static string $keyUpdated = 'updated';
    /**
     * The format of the created and updated timestamps, when saved as a string.
     */
    protected static string $timestampFormat = Api::Format_TimeStamp;
    /**
     * Constants to enable some kind of locking and thereby preventing sending
     * invoices twice.
     */
    protected static int $maxLockTimeS = 40;
    /**
     * @var array|object
     *   The web shop specific data holder for the Acumulus entry.
     */
    protected $record;

    /**
     * constructor.
     *
     * @param array|object $record
     *   A web shop specific record object or array that holds an Acumulus entry
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
    public function getEntryId(): ?int
    {
        // Is it a real entry id or a concept id.
        $token = $this->getToken();

        return !empty($token) && $token !== static::lockToken ? (int) ($this->get(static::$keyEntryId)) : null;
    }

    /**
     * Returns the concept id for this Acumulus entry.
     *
     * Before support for the Acumulus API v4.3.0 was added, concepts were
     * stored as null for token AND entry id. Since it was added they are stored
     * as an int for entry id (actually the concept id) and null for token.
     *
     * @return int|null
     *   The concept id of this Acumulus entry, 0 if it was not stored, or null
     *   if it is a real entry (i.e. not a concept).
     */
    public function getConceptId(): ?int
    {
        // Is it a concept id or a real entry id.
        $token = $this->getToken();

        return empty($token) ? (int) $this->get(static::$keyEntryId) : null;
    }

    /**
     * Returns the entry id for this Acumulus entry.
     *
     * @return string|null
     *   The token for this Acumulus entry or null if it was stored as a
     *   concept.
     */
    public function getToken(): ?string
    {
        return $this->get(static::$keyToken);
    }

    /**
     * Return the type of shop source this Acumulus entry was created for.
     *
     * @return string
     *   The type of shop source being Source::Order or Source::CreditNote.
     *
     * @noinspection PhpUnused
     */
    public function getSourceType(): ?string
    {
        return $this->get(static::$keySourceType);
    }

    /**
     * Returns the id of the shop source this Acumulus entry was created for.
     *
     * @return int
     *   The id of the shop source.
     */
    public function getSourceId(): ?int
    {
        return $this->get(static::$keySourceId);
    }

    /**
     * Returns the time when this record was created.
     *
     * @param bool $raw
     *   Whether to return the raw value as stored in the database, or a
     *   Datetime object. The raw value will differ per web shop.
     *
     * @return string|int|\DateTime
     *   The timestamp when this record was created.
     */
    public function getCreated(bool $raw = false)
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
     *   Datetime object. The raw value will differ per web shop.
     *
     * @return string|int|\DateTime
     *   The timestamp when this record was last updated.
     */
    public function getUpdated(bool $raw = false)
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
            $result->setTimestamp((int) $timestamp);
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
     * As different web shops may use different field and property names in
     * their tables and models, we abstracted accessing a field of a record into
     * this method.
     *
     * @param string $field
     *   The field to search for.
     *
     * @return mixed|null
     *   The value of the given field in this acumulus entry record.
     *
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    protected function get(string $field)
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
                $value = call_user_func([$this->record, $field]);
            } elseif (method_exists($this->record, '__get')) {
                /** @noinspection PhpVariableVariableInspection */
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
    public function isSendLock(): bool
    {
        return $this->getToken() === static::lockToken;
    }

    /** @noinspection PhpDocMissingThrowsInspection
     *
     * Returns whether there is a lock on sending the invoice, but has expired.
     *
     * @return bool
     *   True if the entry indicates that there is a lock on sending the
     *   invoice, but has expired, false otherwise.
     */
    public function hasLockExpired(): bool
    {
        /** @noinspection NullPointerExceptionInspection */
        return $this->isSendLock() && time() - $this->getCreated()->getTimestamp() > static::$maxLockTimeS;
    }
}
