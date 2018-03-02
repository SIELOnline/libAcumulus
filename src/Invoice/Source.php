<?php

namespace Siel\Acumulus\Invoice;

/**
 * Wraps a source for an invoice, typically an order or a credit note.
 *
 * By defining a wrapper around orders from a specific web shop we can:
 * - define unified access to common properties (like reference, date, etc.).
 * - pass them around in a type safe way.
 */
abstract class Source
{
    // Invoice source type constants.
    const Order = 'Order';
    const CreditNote = 'CreditNote';
    const Other = 'Other';

    /** @var string */
    protected $type;

    /** @var int */
    protected $id;

    /** @var array|object */
    protected $source;

    /** @var array|object */
    protected $invoice;

    /**
     * @param string $type
     * @param int|string|array|object $idOrSource
     */
    public function __construct($type, $idOrSource)
    {
        $this->type = $type;
        if (is_scalar($idOrSource)) {
            $this->id = $idOrSource;
            $this->setSource();
        } else {
            $this->source = $idOrSource;
            $this->setId();
        }
        $this->setInvoice();
    }

    /**
     * Returns the type of the wrapped source.
     *
     * @return string
     *   One of the Source constants Source::Order or Source::CreditNote.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the source based on type and id.
     *
     * Should either be overridden or both setSourceOrder() and
     * setSourceCreditNote() should be implemented.
     */
    protected function setSource()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the web shop specific source for an invoice.
     *
     * @return array|object
     *   the web sop specific source for an invoice, either an order or a credit
     *   note object or array.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Sets the id based on type and source.
     *
     * Should either be overridden or both setIdOrder() and
     * setIdCreditNote() should be implemented.
     */
    protected function setId()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the internal id of this invoice source.
     *
     * @return int|string
     *   The internal id.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the user facing reference for this invoice source.
     *
     * Should be overridden when this is not the internal id.
     *
     * @return string|int
     *   The user facing id for this invoice source. This is not necessarily the
     *   internal id!
     */
    public function getReference()
    {
        return $this->getId();
    }

    /**
     * Returns the order (or credit memo) date.
     *
     * @return string
     *   The order (or credit memo) date: yyyy-mm-dd.
     */
    public function getDate()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the status for this invoice source.
     *
     * Should either be overridden or both getStatusOrder() and
     * getStatusCreditNote() should be implemented.
     *
     * @return mixed
     *   The status for this invoice source.
     */
    public function getStatus()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Loads and sets the web shop invoice linked ot this source.
     */
    protected function setInvoice()
    {
        $this->invoice = null;
    }

    /**
     * Returns the web shop invoice linked to this source.
     *
     * @return object|array|null
     *   The web shop invoice linked to this source, or null if no (separate)
     *   invoice is linked to this source.
     */
    protected function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * Returns the number of the web shop invoice linked to this source.
     *
     * @return int|string|null
     *   The reference number of the (web shop) invoice linked to this source,
     *   or null if no invoice is linked to this source.
     */
    public function getInvoiceReference()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReferenceCreditNote()
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getReference();
    }

    /**
     * Returns the date of the web shop invoice linked to this source.
     *
     * @return string|null
     *   Date of the (web shop) invoice linked to this source: yyyy-mm-dd, or
     *   null if no web shop invoice is linked to this source.
     */
    public function getInvoiceDate()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDateCreditNote()
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getDate();
    }

    /**
     * Returns the original invoice source for this invoice source.
     *
     * The base implementation returns the result of getOriginalOrder() for
     * credit notes and null for non credit notes. Normally, a shop should not
     * override this method but override getOriginalOrder() instead.
     *
     * @return Source|null
     *   If the invoice source is a credit note, the original order is returned,
     *   otherwise null.
     */
    public function getOriginalSource()
    {
        $result = null;
        $originalOrder = $this->getOriginalOrder();
        if ($originalOrder !== null) {
            $result = new static(Source::Order, $originalOrder);
        }
        return $result;
    }

    /**
     * Returns the original order or order id for this credit note.
     *
     * The base implementation returns null. Override if the shop supports
     * credit notes.
     *
     * @return array|object|int|null
     *   The original shop order or order id for this credit note, or null if
     *   unknown.
     */
    protected function getOriginalOrder()
    {
        return null;
    }

    /**
     * Calls a "sub" method whose logic depends on the type of invoice source.
     *
     * This allows to separate logic for different source types into different
     * methods.
     *
     * The method name is expected to be the original method name suffixed with
     * the source type.
     *
     * @param string $method
     *   The original method called.
     *
     * @return mixed
     */
    protected function callTypeSpecificMethod($method)
    {
        $method .= $this->getType();
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
