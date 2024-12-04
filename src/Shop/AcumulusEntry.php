<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection DateException is PHP8.3
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
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
    protected object|array $record;

    /**
     * Constructor.
     *
     * @param array|object $record
     *   A web shop specific record object or array that holds an Acumulus entry
     *   record.
     */
    public function __construct(object|array $record)
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
        $token = $this->get(static::$keyToken);
        // WorDPress cannot store null as meta value, so we store(d) '' for an empty
        // token, although the meta key can also be just not set.
        return !empty($token) ? $token : null;
    }

    /**
     * Return the type of shop source this Acumulus entry was created for.
     *
     * @return string
     *   The type of shop source: Source::Order or Source::CreditNote.
     *
     * @noinspection PhpUnused
     */
    public function getSourceType(): string
    {
        return $this->get(static::$keySourceType);
    }

    /**
     * Returns the id of the shop source this Acumulus entry was created for.
     *
     * @return int
     *   The id of the shop source.
     */
    public function getSourceId(): int
    {
        return (int) $this->get(static::$keySourceId);
    }

    /**
     * Returns the time when this record was created.
     *
     * @param bool $raw
     *   Whether to return the raw value as stored in the database, or a DateTimeInterface
     *   object. The raw value will differ per web shop.
     *
     * @return string|int|\DateTimeInterface
     *   The timestamp when this record was created.
     *
     * @throws \DateException|\RuntimeException (PHP8.3: DateException)
     */
    public function getCreated(bool $raw = false): DateTimeInterface|string|int
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
     *   Whether to return the raw value as stored in the database, or a DatetimeInterface
     *   object. The raw value will differ per web shop.
     *
     * @return string|int|\DateTimeInterface
     *   The timestamp when this record was last updated.
     *
     * @throws \DateException|\RuntimeException (PHP8.3: DateException)
     */
    public function getUpdated(bool $raw = false): DateTimeInterface|string|int
    {
        $result = $this->get(static::$keyUpdated);
        // [SIEL #207319]: TypeError: DateTime::createFromFormat() expects parameter 2 to
        // be string, null given in src/Shop/AcumulusEntry.php:230. No idea how or why
        // this can occur, but let's just take the created value for the updated value.
        if (empty($result)) {
            $result = $this->getCreated($raw);
        } elseif (!$raw) {
            $result = $this->toDateTime($result);
        }
        return $result;
    }

    /**
     * Returns a DateTimeInterface object based on the timestamp in database format.
     *
     * @param float|int|string $timestamp
     *
     * @return bool|\DateTimeInterface
     *
     * @throws \DateException|\RuntimeException (PHP8.3: DateException)
     */
    protected function toDateTime(float|int|string $timestamp): DateTimeInterface|bool
    {
        $timestamp = (string) $timestamp;
        if (ctype_digit($timestamp)) {
            // Unix timestamp.
            $result = DateTimeImmutable::createFromFormat('U', $timestamp);
        } elseif (is_numeric($timestamp)) {
            // Unix timestamp.
            $result = DateTimeImmutable::createFromFormat('U.u', $timestamp);
        } else {
            // Formatted timestamp, e.g. yyyy-mm-dd hh:mm:ss. Is assumed to be in the
            // timezone of the webshop if no timezone is specified in the string to parse.
            $result = DateTimeImmutable::createFromFormat(static::$timestampFormat, $timestamp, $this->getDefaultTimeZone());
            if ($result === false) {
                throw new RuntimeException("Failed to convert timestamp string '$timestamp'.");
            }
        }
        return $result;
    }

    /**
     * Returns the default time zone as used by the shop.
     *
     * The default implementation returns the default time zone of the web server.
     *
     * @throws \DateInvalidTimeZoneException
     */
    protected function getDefaultTimeZone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Returns the shop specific record for this Acumulus entry.
     *
     * This getter should only be used by the AcumulusEntryManager.
     *
     * @return array|object
     *   The shop specific record for this Acumulus entry.
     */
    public function getRecord(): object|array
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
    protected function get(string $field): mixed
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
            if (array_key_exists($field, $properties)) {
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

    /**
     * Returns whether there is a lock on sending the invoice, but has expired.
     *
     * @return bool
     *   True if the entry indicates that there is a lock on sending the
     *   invoice, but has expired, false otherwise.
     */
    public function hasLockExpired(): bool
    {
        try {
            return $this->isSendLock() && time() - $this->getCreated()->getTimestamp() > static::$maxLockTimeS;
        } catch (DateException|RuntimeException) {
            return false;
        }
    }
}
