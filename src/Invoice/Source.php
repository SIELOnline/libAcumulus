<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Meta;

/**
 * A wrapper around a webshop order or refund.
 *
 * Source is used to pass an order or refund object (or array) around in a
 * strongly typed way and to provide unified access to information about the
 * order or refund.
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
     * Constructor
     *
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
     * Sets the source based on type and id.
     */
    protected function setSource()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Sets the id based on type and source.
     */
    protected function setId()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the type of the wrapped source.
     *
     * @return string
     *   One of the constants Source::Order or Source::CreditNote.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the webshop specific source for an invoice.
     *
     * @return array|object
     *   The webshop specific source for an invoice.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Returns the internal id of the webshop's invoice source.
     *
     * @return int|string
     *   The internal id of the webshop's invoice source.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the user facing reference for the webshop's invoice source.
     *
     * Should be overridden when this is not the internal id.
     *
     * @return string|int
     *   The user facing id for the webshop's invoice source. This is not
     *   necessarily the internal id.
     */
    public function getReference()
    {
        return $this->getId();
    }

    /**
     * Returns the sign to use for amounts that normally are always defined as a
     * positive number, also on credit notes.
     *
     * @return float
     *   1 for orders, -1 for credit notes (unless the amounts or quantities on
     *   the webshop's credit notes are already negative).
     */
    public function getSign()
    {
        return (float) ($this->getType() === Source::CreditNote ? -1.0 : 1.0);
    }

    /**
     * Returns the webshop's invoice source date.
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
     * The Acumulus plugin does not define its own statuses, so 1 of the
     * webshop's order or credit note statuses should be returned.
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
     * This default implementation returns the payment method for the order as
     * several webshops do not store a payment method with credit notes but
     * instead assume it is the same as for its original order.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    public function getPaymentMethod()
    {
        return $this->getOrder()->getPaymentMethodOrder();
    }

    /**
     * Returns the payment method used for this order.
     *
     * This method will only be called when $this represents an order.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    public function getPaymentMethodOrder()
    {
        throw new \RuntimeException('Source::getPaymentMethodOrder() not implemented for ' . get_class($this));
    }

    /**
     * @deprecated: use Source::getPaymentStatus()
     */
    public function getPaymentState()
    {
        return $this->getPaymentStatus();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    public function getPaymentStatus()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the payment date.
     *
     * The payment date is defined as the date on which the status changed from
     * the non-paid status to the paid status. If there are multiple status
     * changes, the last one should be taken.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been (fully)
     *   paid yet.
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
     * Do not override this method but implement getAvailableTotals() instead.
     *
     * @return array
     *   An array with the following possible keys:
     *   - meta-invoice-amount: the total invoice amount excluding VAT.
     *   - meta-invoice-amountinc: the total invoice amount including VAT.
     *   - meta-invoice-vatamount: the total vat amount for the invoice.
     *
     *   This one is really optional: so far only filled by OpenCart and
     *   purely for reasons of support:
     *   - meta-invoice-vat-breakdown: a vat breakdown per vat rate.
     */
    public function getTotals()
    {
        $result = $this->getAvailableTotals();
        $result = $this->completeTotals($result);
        return $result;
    }

    /**
     * Returns an array with the available totals fields.
     *
     * Most webshops provide only 2 of the 3 totals, so only return those that
     * are provided. Source::getTotals() will complete missing fields by calling
     * Source::completeTotals();
     *
     * @return array
     *   An array with the following possible keys:
     *   - meta-invoice-amount: the total invoice amount excluding VAT.
     *   - meta-invoice-amountinc: the total invoice amount including VAT.
     *   - meta-invoice-vatamount: the total vat amount for the invoice.
     *
     *   This one is really optional: so far only filled by OpenCart and
     *   purely for reasons of support:
     *   - meta-invoice-vat-breakdown: a vat breakdown per vat rate.
     */
    abstract protected function getAvailableTotals();

    /**
     * Completes the set of invoice totals as set by getInvoiceTotals.
     *
     * Most shops only provide 2 out of these 3 in their data, so we calculate
     * the 3rd.
     *
     * Do not override this method, just implement getAvailableTotals().
     *
     * @param array $totals
     *   The invoice totals to complete with missing total fields.
     *
     * @return array
     *   The invoice totals with all invoice total fields.
     */
    protected function completeTotals(array $totals)
    {
        if (!isset($totals[Meta::InvoiceAmount])) {
            $totals[Meta::InvoiceAmount] = $totals[Meta::InvoiceAmountInc] - $totals[Meta::InvoiceVatAmount];
            $totals[Meta::InvoiceCalculated ] = Meta::InvoiceAmount;
        }
        if (!isset($totals[Meta::InvoiceAmountInc])) {
            $totals[Meta::InvoiceAmountInc] = $totals[Meta::InvoiceAmount] + $totals[Meta::InvoiceVatAmount];
            $totals[Meta::InvoiceCalculated ] = Meta::InvoiceAmountInc;
        }
        if (!isset($totals[Meta::InvoiceVatAmount])) {
            $totals[Meta::InvoiceVatAmount] = $totals[Meta::InvoiceAmountInc] - $totals[Meta::InvoiceAmount];
            $totals[Meta::InvoiceCalculated ] = Meta::InvoiceVatAmount;
        }
        return $totals;
    }

    /**
     * Loads and sets the web shop invoice linked to this source.
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
     * Returns the reference of the web shop invoice linked to this source.
     *
     * @return int|string|null
     *   The reference of the (web shop) invoice linked to this source, or null
     *   if no invoice is linked to this source.
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
     * Do not override this method but override getShopOrderOrId() instead.
     *
     * @return Source
     *   If the invoice source is a credit note, its original order is returned,
     *   otherwise, the invoice source is an order itself and $this is returned.
     */
    public function getOrder()
    {
        return $this->getType() === Source::CreditNote ? new static(Source::Order, $this->getShopOrderOrId()) : $this;
    }

    /** @noinspection PhpDocSignatureInspection */
    /**
     * Returns the original order or order id for this credit note.
     *
     * This method will only be called when $this represents a credit note.
     *
     * The base implementation throws an exception for those webshops that do
     * not support credit notes. Override if the webshop supports credit notes.
     * Do not do any object loading here if only the id is readily available.
     *
     * @return array|object|int
     *   The original order itself, if readily available, or the id of the
     *   original order for this credit note.
     */
    protected function getShopOrderOrId()
    {
        throw new \RuntimeException('Source::getShopOrderOrId() not implemented for ' . get_class($this));
    }

    /**
     * Returns the set of credit note sources for an order source.
     *
     * Do not override this method but override getShopCreditNotes() instead.
     *
     * @return Source[]|null
     *   If the invoice source is an order, an array of refunds is returned,
     *   null otherwise.
     */
    public function getCreditNotes()
    {
        $result = null;
        if ($this->getType() === Source::Order) {
            $result = array();
            $shopCreditNotes = $this->getShopCreditNotesOrIds();
            foreach ($shopCreditNotes as $shopCreditNote) {
                $result[] = new static(Source::CreditNote, $shopCreditNote);
            }
        }
        return $result;
    }

    /**
     * Returns the credit notes or credit note ids for this order.
     *
     * This method will only be called when $this represents an order.
     *
     * The base implementation returns an empty array for those webshops that do
     * not support credit notes. Override if the webshop supports credit notes.
     * Do not do any object loading here if only the ids are readily available.
     *
     * @return array[]|object[]|int[]|\Traversable
     *   The, possibly empty, set of refunds or refund-ids for this order.
     */
    protected function getShopCreditNotesOrIds()
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
     * the source type (Order or CreditNote).
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
