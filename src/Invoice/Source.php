<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Meta;

use function get_class;

/**
 * A wrapper around a web shop order or refund.
 *
 * Source is used to pass an order or refund object (or array) around in a
 * strongly typed way and to provide unified access to information about the
 * order or refund.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
abstract class Source
{
    // Invoice source type constants.
    public const Order = 'Order';
    public const CreditNote = 'CreditNote';
    public const Other = 'Other';

    protected string $type;
    /**
     * @todo
     *   Make these an int resp. array|object, thus not null. This means, we
     *   should probably throw when we cannot construct a valid instance
     */
    protected ?int $id;
    /** @var array|object|null */
    protected $source;
    /** @var array|object|null */
    protected $invoice;

    /**
     * Constructor.
     *
     * @param string $type
     * @param int|string|array|object $idOrSource
     *
     * @todo
     *   Throw an exception if we cannot find the source.
     */
    public function __construct(string $type, $idOrSource)
    {
        $this->type = $type;
        if (empty($idOrSource)) {
            $this->id = null;
            $this->source = null;
        } elseif (is_scalar($idOrSource)) {
            $this->id = (int) $idOrSource;
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
    protected function setSource(): void
    {
        $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Sets the id based on type and source.
     */
    protected function setId(): void
    {
        $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns whether the wrapped source is a valid source.
     *
     * This should mainly be used directly after creating a Source from
     * non-trusted input.
     *
     * @return bool
     *   True if the wrapped source is a valid source, false otherwise.
     *
     * @noinspection PhpUnused
     */
    public function isValid(): bool
    {
        return $this->id !== null && $this->source !== null;
    }

    /**
     * Returns the type of the wrapped source.
     *
     * @return string
     *   One of the constants Source::Order or Source::CreditNote.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the web shop specific source for an invoice.
     *
     * @return array|object|null
     *   The web shop specific source for an invoice.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Returns the internal id of the web shop's invoice source.
     *
     * @return int
     *   The internal id of the web shop's invoice source.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the user facing reference for the web shop's invoice source.
     *
     * Should be overridden when this is not the internal id.
     *
     * @return string|int
     *   The user facing id for the web shop's invoice source. This is not
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
     *   the web shop's credit notes are already negative).
     */
    public function getSign(): float
    {
        return $this->getType() === static::CreditNote ? -1.0 : 1.0;
    }

    /**
     * Returns the web shop's invoice source date.
     *
     * @return string
     *   The order (or credit memo) date: yyyy-mm-dd.
     */
    public function getDate(): string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the status for this invoice source.
     *
     * The Acumulus plugin does not define its own statuses, so 1 of the
     * web shop's order or credit note statuses should be returned.
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
     * several web shops do not store a payment method with credit notes but
     * instead assume it is the same as for its original order.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    public function getPaymentMethod()
    {
        return $this->getOrder()->getPaymentMethod();
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
        throw new RuntimeException('Source::getPaymentMethodOrder() not implemented for ' . get_class($this));
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    public function getPaymentStatus(): int
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
    public function getPaymentDate(): ?string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the country code for the order.
     *
     * @return string
     *   The 2-letter country code for the current order or the empty string if
     *   not set.
     */
    abstract public function getCountryCode(): string;

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
    abstract public function getCurrency(): array;

    /**
     * Returns an array with the totals fields.
     *
     * Do not override this method but implement getAvailableTotals() instead.
     *
     * @return array
     *   An array with the following possible keys:
     *   - 'meta-invoice-amount': the total invoice amount excluding VAT.
     *   - 'meta-invoice-amountinc': the total invoice amount including VAT.
     *   - 'meta-invoice-vatamount': the total vat amount for the invoice.
     *
     *   This one is really optional: so far only filled by OpenCart and
     *   purely for reasons of support:
     *   - 'meta-invoice-vat-breakdown': a vat breakdown per vat rate.
     */
    public function getTotals(): array
    {
        return $this->completeTotals($this->getAvailableTotals());
    }

    /**
     * Returns an array with the available totals fields.
     *
     * Most web shops provide only 2 of the 3 totals, so only return those that
     * are provided. Source::getTotals() will complete missing fields by calling
     * Source::completeTotals();
     *
     * @return array
     *   An array with the following possible keys:
     *   - 'meta-invoice-amount': the total invoice amount excluding VAT.
     *   - 'meta-invoice-amountinc': the total invoice amount including VAT.
     *   - 'meta-invoice-vatamount': the total vat amount for the invoice.
     *
     *   This one is really optional: so far only filled by OpenCart and
     *   purely for reasons of support:
     *   - 'meta-invoice-vat-breakdown': a vat breakdown per vat rate.
     */
    abstract protected function getAvailableTotals(): array;

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
    protected function completeTotals(array $totals): array
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
    protected function setInvoice(): void
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
     * See {@see getInvoiceReference}
     *
     * @noinspection PhpUnused
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
    public function getInvoiceDate(): ?string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * See {@see getInvoiceDate}
     *
     * @noinspection PhpUnused
     */
    public function getInvoiceDateCreditNote(): ?string
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
    public function getOrder(): Source
    {
        return $this->getType() === static::CreditNote ? new static(static::Order, $this->getShopOrderOrId()) : $this;
    }

    /** @noinspection PhpDocSignatureInspection */
    /**
     * Returns the original order or order id for this credit note.
     *
     * This method will only be called when $this represents a credit note.
     *
     * The base implementation throws an exception for those web shops that do
     * not support credit notes. Override if the web shop supports credit notes.
     * Do not do any object loading here if only the id is readily available.
     *
     * @return array|object|int
     *   The original order itself, if readily available, or the id of the
     *   original order for this credit note.
     */
    protected function getShopOrderOrId()
    {
        throw new RuntimeException('Source::getShopOrderOrId() not implemented for ' . get_class($this));
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
    public function getCreditNotes(): ?array
    {
        $result = null;
        if ($this->getType() === static::Order) {
            $result = [];
            $shopCreditNotes = $this->getShopCreditNotesOrIds();
            foreach ($shopCreditNotes as $shopCreditNote) {
                $result[] = new static(static::CreditNote, $shopCreditNote);
            }
        }
        return $result;
    }

    /**
     * Returns the credit notes or credit note ids for this order.
     *
     * This method will only be called when $this represents an order.
     *
     * The base implementation returns an empty array for those web shops that
     * do not support credit notes. Override if the web shop does support credit
     * notes. Do not do any object loading here if only the ids are readily
     * available.
     *
     * @return array[]|object[]|int[]|\Traversable
     *   The, possibly empty, set of refunds or refund-ids for this order.
     */
    protected function getShopCreditNotesOrIds()
    {
        return [];
    }

    /**
     * Calls a "sub" method whose logic depends on the type of invoice source.
     * This allows to separate logic for different source types into different
     * methods.
     * The method name is expected to be the original method name suffixed with
     * the source type (Order or CreditNote).
     *
     * @param string $method
     *   The name of the base method for which to call the Source type specific
     *   variant.
     * @param array $args
     *   The arguments to pass to the method to call.
     *
     * @return mixed
     *   The return value of that method call, or null if the method does not
     *   exist.
     */
    protected function callTypeSpecificMethod(string $method, array $args = [])
    {
        $method .= $this->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
