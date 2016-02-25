<?php

namespace Siel\Acumulus\Invoice;

/**
 * Wraps a source for an invoice, typically an order or a credit note.
 *
 * By defining a wrapper around orders from a specific web shop we can more
 * easily (and in a type safe way) pass them around.
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
     * Returns the source for an invoice, either an order or a credit note object.
     *
     * @return array|object
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
     * Calls a method that typically depends on the type of invoice source.
     *
     * @param string $method
     *
     * @return void|mixed
     */
    protected function callTypeSpecificMethod($method)
    {
        $method .= $this->getType();
        return $this->$method();
    }
}
