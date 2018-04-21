<?php
namespace Siel\Acumulus\Shop;

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
        return $this->get(static::$keyEntryId);
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
     * @return string
     *   The timestamp when this record was created.
     */
    public function getCreated()
    {
        return $this->get(static::$keyCreated);
    }

    /**
     * Returns the time when this record was last updated.
     *
     * @return string
     *   The timestamp when this record was last updated.
     */
    public function getUpdated()
    {
        return $this->get(static::$keyUpdated);
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
}
