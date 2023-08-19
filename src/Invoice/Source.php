<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;

use function count;
use function function_exists;
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
    protected int $id;
    /** @var array|object */
    protected $shopSource;
    protected Source $orderSource;
    /** @var array|object|null */
    protected $invoice;

    /**
     * Constructor.
     *
     * @param string $type
     * @param int|string|array|object $idOrSource
     *
     * @throws  \RuntimeException
     *   If $idOrSource is empty or not a valid source.
     */
    public function __construct(string $type, $idOrSource)
    {
        $this->type = $type;
        if (empty($idOrSource)) {
            throw new RuntimeException('Empty source');
        } elseif (is_scalar($idOrSource)) {
            $this->id = (int) $idOrSource;
            $this->setSource();
        } else {
            $this->shopSource = $idOrSource;
            $this->setId();
        }
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
     * Returns the translated type of the wrapped source.
     *
     * @param int $case
     *   - MB_CASE_LOWER (1): convert to all lower case
     *   - MB_CASE_UPPER (0): convert to all upper case
     *   - MB_CASE_TITLE (2): convert first character to upper case
     *   any other value or not passed: return as is, do not convert
     *
     * @return string
     *   One of the constants Source::Order or Source::CreditNote.
     *
     * @noinspection PhpUnused   May be called via the
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}.
     */
    public function getTypeLabel(int $case = -1): string
    {
        $label = Container::getContainer()->getTranslator()->get($this->getType());
        if ($case !== -1) {
            if (function_exists('mb_convert_case')) {
                $label = mb_convert_case($label, $case);
            } else {
                switch ($case) {
                    case MB_CASE_LOWER:
                        $label = strtolower($label);
                        break;
                    case MB_CASE_UPPER:
                        $label = strtoupper($label);
                        break;
                    case MB_CASE_TITLE:
                        $label = ucfirst($label);
                        break;
                }
            }
        }
        return $label;
    }

    /**
     * Returns the internal id of the web shop's invoice source.
     *
     * @return int
     *   The internal id of the web shop's invoice source.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Sets the id based on type and source.
     */
    protected function setId(): void
    {
        $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the web shop specific source for an invoice.
     *
     * @return array|object
     *   The web shop specific source for an invoice.
     */
    public function getSource()
    {
        return $this->shopSource;
    }

    /**
     * Sets the web shop specific source based on type and id.
     *
     * Only called by the constructor as this wrapper object should be
     * "immutable": it should only represent one source over its lifetime.
     *
     * @throws \RuntimeException
     */
    protected function setSource(): void
    {
        $this->callTypeSpecificMethod(__FUNCTION__);
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
        return $this->getType() === Source::CreditNote ? -1.0 : 1.0;
    }

    /**
     * Returns the web shop's order or refund date.
     *
     * @return string
     *   The order (or credit memo) date: yyyy-mm-dd.
     *
     * @todo: convert to type DateTime
     */
    public function getDate(): string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the status for this invoice source.
     *
     * The Acumulus plugin does not define its own statuses, so one of the
     * web shop's order or credit note statuses should be returned.
     *
     * Should either be overridden or both getStatusOrder() and
     * getStatusCreditNote() should be implemented.
     *
     * @return int|string
     *   The status for this invoice source.
     */
    public function getStatus()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the payment method used.
     *
     * Should either be overridden or both getPaymentMethodOrder() and
     * getPaymentMethodNote() should be implemented.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    public function getPaymentMethod()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the payment method used for this credit note.
     *
     * This default implementation returns the payment method for the order as
     * several web shops do not store a payment method with credit notes but
     * instead assume it is the same as for its original order.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     *
     * @noinspection PhpUnused  Called via callTypeSpecificMethod().
     */
    public function getPaymentMethodCreditNote()
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
     *
     * @todo: convert to type DateTime
     */
    public function getPaymentDate(): ?string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the country code for the order.
     *
     * The return value is not necessarily in upper case.
     *
     * @return string
     *   The 2-letter country code for the current order or the empty string if
     *   not set.
     */
    abstract public function getCountryCode(): string;

    /**
     * Returns info about the used currency on this order/refund.
     *
     * The currency related info:
     * - currency: the code of the currency used for this order/refund
     * - rate: the rate from the used currency to the shop's default
     *   currency.
     * - doConvert: if the amounts are in the used currency or in the
     *   default currency (MA, OC, WC).
     *
     * This default implementation is for shops that do not support multiple
     * currencies. This means that the amounts are always in the shop's default
     * currency (which should be EUR). Even if another plugin is used to present
     * another currency to the customer, the amounts stored should still be in
     * EUR. So, we will not have to convert amounts and this meta info is thus
     * purely informative.
     */
    public function getCurrency(): Currency
    {
        // Constructor defaults are geared for the case that no conversion has
        // to be done.
        return new Currency();
    }

    /**
     * Returns a {@see Totals} object with the invoice totals.
     */
    abstract public function getTotals(): Totals;

    /**
     * Loads and sets the web shop invoice linked to this source.
     *
     * This default implementation assumes that the web shop does not have
     * (separate) invoices. Override if your shop does offer invoices.
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
        // Lazy loading.
        if (!isset($this->invoice)) {
            $this->setInvoice();
        }
        return $this->invoice;
    }

    /**
     * Returns the id of the web shop invoice linked to this source.
     *
     * This base implementation will return null, invoices not supported. So,
     * override if a shop supports invoices as proper objects on their own,
     * stored under their own id.
     *
     * @return int|null
     *   The id of the (web shop) invoice linked to this source, or null
     *   if no invoice is linked to this source.
     */
    public function getInvoiceId(): ?int
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * See {@see getInvoiceId()}
     *
     * @noinspection PhpUnused  Called via callTypeSpecificMethod().
     */
    public function getInvoiceIdCreditNote(): ?int
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getId();
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
     * See {@see getInvoiceReference()}
     *
     * @noinspection PhpUnused  Called via callTypeSpecificMethod().
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
     * @noinspection PhpUnused  Called via callTypeSpecificMethod().
     *
     * @todo: convert to type DateTime
     */
    public function getInvoiceDateCreditNote(): ?string
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getDate();
    }

    /**
     * Returns a {@see Source} for the order of a credit note.
     *
     * Do not override this method but override getShopOrderOrId() instead.
     *
     * @return Source
     *   If the invoice source is a credit note, its original order is returned,
     *   otherwise, the invoice source is an order itself and $this is returned.
     */
    public function getOrder(): Source
    {
        if (!isset($this->orderSource)) {
            $this->orderSource = $this->getType() === Source::Order
                ? $this
                : new static(Source::Order, $this->getShopOrderOrId());
        }
        return $this->orderSource;
    }

    /**
     * Returns the parent {@see Source} for a credit note.
     *
     * This is typically used in mappings, that do not allow condition testing
     * other than canceling the property/method traversal when null is returned.
     *
     * Do not override this method but override getShopOrderOrId() instead.
     *
     * @return Source|null
     *   If the invoice source is a credit note, its original order is returned,
     *   otherwise, null.
     */
    public function getParent(): ?Source
    {
        return $this->getType() !== Source::Order ? $this->getOrder() : null;
    }

    /**
     * Returns $this if the current object is of the given type.
     *
     * @param string $type
     *   One of the Source constants used to define the type of the source
     *   (Order, CreditNote, Invoice (future)).
     *
     * @return \Siel\Acumulus\Invoice\Source|null
     *   $this if the current object is of the given type, null otherwise.
     */
    protected function isType(string $type): ?Source
    {
        return $this->getType() === $type ? $this : null;
    }

    /**
     * @noinspection PhpUnused  May be called via the {@see \Siel\Acumulus\Helpers\FieldExpander}.
     */
    public function isOrder(): ?Source
    {
        return $this->isType(Source::Order);
    }

    /**
     * @noinspection PhpUnused  May be called via the {@see \Siel\Acumulus\Helpers\FieldExpander}.
     */
    public function isCreditNote(): ?Source
    {
        return $this->isType(Source::CreditNote);
    }

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
     * @return Source[]
     *   If this invoice source is a(n):
     *   - Order: a - possibly empty - array of credit notes of this order.
     *   - Credit note: an array with this credit note as only element
     */
    public function getCreditNotes(): array
    {
        if ($this->getType() === Source::Order) {
            $result = [];
            $shopCreditNotes = $this->getShopCreditNotesOrIds();
            foreach ($shopCreditNotes as $shopCreditNote) {
                $result[] = new static(Source::CreditNote, $shopCreditNote);
            }
        } else {
            $result = [$this];
        }
        return $result;
    }

    /**
     * Returns the credit notes or credit note ids for this order.
     *
     * This method will only be called when $this represents an order.
     *
     * The base implementation returns an empty array for those web shops that
     * do not support credit notes. Override if the web shop supports credit
     * notes. Do not do any object loading here if only the ids are readily
     * available.
     *
     * @return array[]|object[]|int[]|\Traversable
     *   The - possibly empty - set of refunds or refund-ids for this order.
     */
    protected function getShopCreditNotesOrIds()
    {
        return [];
    }

    /**
     * Returns a credit note for this invoice source.
     *
     * @param int $index
     *   The 0-based index of the credit note to return. Some shops allow for
     *   more than 1 credit note to be created for any given order. By default,
     *   the 1st (and often the only possible one) will be returned.
     *
     * @return array|object|null
     *   If this invoice source is a(n):
     *   - Order that has at least $index+1 credit notes: the ith credit note
     *     for this order.
     *   - Credit note: if $index = 0, the credit note itself, otherwise null.
     *
     * @noinspection PhpUnused  Can be used in mappings.
     */
    public function getCreditNote(int $index = 0)
    {
        $creditNotes = $this->getCreditNotes();
        return $index < count($creditNotes) ? $creditNotes[$index]->getSource() : null;
    }

    /**
     * Calls a type specific implementation of $method.
     *
     * This allows to separate logic for different source types into different
     * methods. The method name is expected to be the original method name
     * suffixed with the source type (Order or CreditNote).
     *
     * @param string $method
     *   The name of the base method for which to call the Source type specific
     *   variant.
     * @param mixed $args
     *   The arguments to pass to the method to call.
     *
     * @return mixed
     *   The return value of that method call, or null if the method does not
     *   exist.
     *
     * @todo: all methods called via this method can be made protected/private.
     */
    protected function callTypeSpecificMethod(string $method, ...$args)
    {
        $method .= $this->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
