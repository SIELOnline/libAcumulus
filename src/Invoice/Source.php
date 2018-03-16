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
     * Returns the sign to use for amounts that are always defined as a positive
     * number, also on credit notes.
     *
     * @return float
     *   1 for orders, -1 for credit notes.
     */
    public function getSign()
    {
        return (float) ($this->getType() === Source::CreditNote ? -1.0 : 1.0);
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
     * Returns the payment method used.
     *
     * This default implementation returns an empty payment method.
     *
     * If no payment method is stored for credit notes, it is expected to be the
     * same as for its order, as this will normally indeed be the case.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    public function getPaymentMethod()
    {
        return null;
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    public function getPaymentState()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the payment date.
     *
     * The payment date is defined as the date on which the status changed from a
     * non-paid state to a paid state. If there are multiple state changes, the
     * last one is taken.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
     */
    public function getPaymentDate()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the country code for the order.
     *
     * @return string
     *   The 2 letter country code for the current order or the empty string if
     *   not set.
     */
    abstract public function getCountryCode();

    /**
     * Returns metadata about the used currency on the invoice.
     *
     * The currency related meta tags are:
     * - currency: the code of the currency used for this order/refund
     * - currency-rate: the rate from the used currency to the shop's default
     *   currency.
     * - currency-do-convert: if the amounts are in the used currency or in the
     *   default currency (MA, OC, WC).
     *
     * @return array
     *   An array with the currency meta tags.
     */
    abstract public function getCurrency();

    /**
     * Returns an array with the totals fields.
     *
     * All total fields are optional but may be used or even expected by the
     * Completor or are used for support and debugging purposes.
     *
     * This default implementation returns an empty array. Override to provide the
     * values.
     *
     * @return array
     *   An array with the following possible keys:
     *   - meta-invoice-amount: the total invoice amount excluding VAT.
     *   - meta-invoice-amountinc: the total invoice amount including VAT.
     *   - meta-invoice-vatamount: the total vat amount for the invoice.
     *   - meta-invoice-vat: a vat breakdown per vat rate.
     */
    abstract public function getTotals();

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
     * Returns the order source for a credit note source.
     *
     * Do not override this method but override getShopOrder() instead.
     *
     * @return Source
     *   If the invoice source is a credit note, its original order is returned,
     *   otherwise, the invoice source is an order itself and $this is returned.
     */
    public function getOrder()
    {
        return $this->getType() === Source::CreditNote ? new static(Source::Order, $this->getShopOrderId()) : $this;
    }

    /**
     * Returns the original order or order id for this credit note.
     *
     * The base implementation returns $this. Override if the shop supports
     * credit notes and thus the result can be a different order.
     *
     * @return array|object|int
     *   If the invoice source is a credit note, its original shop order is
     *   returned, otherwise, the invoice source is an order, the shop order
     *   itself is returned.
     */
    protected function getShopOrderId()
    {
        return $this->source;
    }

    /**
     * Returns the set of credit note sources for an order source.
     *
     * Do not override this method but override getShopCreditNotes() instead.
     *
     * @return Source[]?
     *   If the invoice source is an order, an array of refunds is returned,
     *   null otherwise.
     */
    public function getCreditNotes()
    {
        $result = null;
        if ($this->getType() === Source::Order) {
            $result = array();
            $shopCreditNotes = $this->getShopCreditNotes();
            foreach ($shopCreditNotes as $shopCreditNote) {
                $result[] = new static(Source::CreditNote, $shopCreditNote);
            }
        }
        return $result;
    }

    /**
     * Returns the credit notes or credit note ids for this order.
     *
     * The base implementation returns null. Override if the shop supports
     * credit notes.
     *
     * @return array[]|object[]|int[]|\Traversable null
     *   The, possibly empty, set of refunds or refund-ids for this order, or
     *   null if not supported.
     */
    protected function getShopCreditNotes()
    {
        return array();
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
     * @param array $args
     *   The parameters to pass to the type specific method.
     *
     * @return mixed
     */
    protected function callTypeSpecificMethod($method, $args = array())
    {
        $method .= $this->getType();
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }
        return null;
    }
}
