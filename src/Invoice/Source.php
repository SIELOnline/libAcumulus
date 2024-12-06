<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Product\Product;
use Stringable;

use function count;
use function get_class;

/**
 * Source is an adapter (and wrapper) class around a web shop order or refund.
 *
 * Source is used to provide unified access to information about an order or refund from
 * the web shop. Furthermore, by wrapping it in a single, library defined, object type,
 * web shop orders and refunds can be passed around in a strongly typed way.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
abstract class Source implements WrapperInterface, Stringable
{
    use WrapperTrait;

    // Invoice source type constants.
    public const Order = 'Order';
    public const CreditNote = 'CreditNote';
    public const Other = 'Other';

    protected string $type;
    protected Source $orderSource;
    protected object|array|null $invoice;
    protected ?array $items;

    /**
     * Constructor.
     *
     * @throws \RuntimeException
     *   If $idOrSource is empty or not a valid source.
     */
    public function __construct(string $type, int|string|object|array|null $idOrObject, Container $container)
    {
        $this->type = $type;
        $this->initializeWrapper($idOrObject, $container);
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

    public function __toString(): string
    {
        return $this->getType() . $this->getId();
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
     * @return int|string|null
     *   The status for this invoice source, null if no status is known
     */
    public function getStatus(): int|string|null
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
    public function getPaymentMethod(): int|string|null
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
    protected function getPaymentMethodCreditNote(): int|string|null
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
    protected function getPaymentMethodOrder(): int|string|null
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
     * Returns VAT breakdown metadata (a breakdown of the total vat amount per tax
     * class/rate).
     *
     * The default implementation returns null, i.e. no vat breakdown information.
     * Override if the shop needs and has this info. Currently only HS and OC override
     * this.
     */
    public function getVatBreakdown(): ?array
    {
        return null;
    }

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
    protected function getInvoice(): object|array|null
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
    protected function getInvoiceIdCreditNote(): ?int
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
    public function getInvoiceReference(): int|string|null
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * See {@see getInvoiceReference()}
     *
     * @noinspection PhpUnused  Called via callTypeSpecificMethod().
     */
    protected function getInvoiceReferenceCreditNote(): int|string
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
     *
     * @todo: convert to type DateTime
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
    protected function getInvoiceDateCreditNote(): ?string
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
                : $this->getContainer()->createSource(Source::Order, $this->getShopOrderOrId());
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
    protected function getShopOrderOrId(): object|array|int
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
                $result[] = $this->getContainer()->createSource(Source::CreditNote, $shopCreditNote);
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
     * @return \iterable
     *   The - possibly empty - set of refunds or refund-ids for this order.
     */
    protected function getShopCreditNotesOrIds(): iterable
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
    public function getCreditNote(int $index = 0): object|array|null
    {
        $creditNotes = $this->getCreditNotes();
        return $index < count($creditNotes) ? $creditNotes[$index]->getShopObject() : null;
    }

    /**
     * Returns the item lines for this Source.
     *
     * @return Item[]
     */
    public function getItems(): array
    {
        if (!isset($this->items)) {
            $this->items = $this->createItems();
        }
        return $this->items;
    }

    /**
     * Creates the {@see Item}s ordered on this {@see Source}.
     *
     * Overrides can use the {@see getShopObject()} method to get the shop order and
     * retrieve the item lines. If no item lines exist, which is highly unlikely, an empty
     * array should be returned.
     *
     * Normally, this method will be called only once by the public method
     * {@see getItems()}, so it is correct to create new instances.
     *
     * @return Item[]
     */
    protected function createItems(): array
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns a set of "shipping infos".
     *
     * A shipping info is an "object" that contains information about a shipping line for
     * this invoice source. What this info looks like is shop dependent, e.g:
     * - In many shops shipping info is stored at the order level, so this source is
     *   returned as one and only array value, but only if a shipping took place.
     * - In other shops, shipments may be stored as "total lines", "cart rules", or
     *   something like that and this method would return an array of those "total lines"
     *   (or whatever they are called) that represent a shipping.
     *
     * As the data structures returned are fully shop dependent, so should the processing
     * code be. However, defining this method allows the managing code to be shop
     * independent and thus be placed in the base
     * {@see \Siel\Acumulus\Collectors\CollectorManager}.
     *
     * [Note: a next step would be to create a wrapper/adapter object, e.g. a ShippingItem
     * like we did for {@see Source}, {@see Item}, and {@see Product}, but that only makes
     * senses if we could make methods (like getDescription(), getQuantity(),
     * getUnitPrice(), etc) on them that works for more or less all shops.]
     *
     * This base implementation typically returns an empty set: no shipping lines.
     * Override to return the shop specific set of shipping infos or implement both
     * getShippingLineInfosOrder() and getShippingLineInfosCreditNote().
     *
     * @return array
     *   The - possibly empty - set of shipping infos. The type of the array entries
     *   differs per shop and may even differ within the list of 1 shop.
     *
     * @noinspection PhpUnused  called via
     *    {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getShippingLineInfos(): array
    {
        return [];
    }

    /**
     * @noinspection PhpUnused  called via
     *   {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getGiftWrappingFeeLineInfos(): array
    {
        return [];
    }

    /**
     * @noinspection PhpUnused  called via
     *   {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getPaymentFeeLineInfos(): array
    {
        return [];
    }

    /**
     * @noinspection PhpUnused  called via
     *   {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getOtherLineInfos(): array
    {
        return [];
    }

    /**
     * Returns a set of "discount infos".
     *
     * Comparable with shipping infos, see {@see Source::getShippingLineInfos()} for more
     * explanation.
     *
     * @return array
     *   The - possibly empty - set of discount infos. The type of the array entries
     *   differs per shop and may even differ within the list of 1 shop.
     *
     * @noinspection PhpUnused  called via
     *    {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getDiscountLineInfos(): array
    {
        return [];
    }

    /**
     * Manual lines are lines that were entered manually. This may occur especially on
     * refunds, e.g. when a certain product or fee is only partially refunded, or if a
     * shop has no way to refund certain (non-product) lines from an order.
     *
     * Manual lines may appear on credit notes to overrule amounts as calculated by the
     * system. E.g.
     * - A product retour after the guaranteed free retour period is over is only
     *   partially refunded.
     * - Discounts applied to a selection of items should only be refunded partially (the
     *   part that is returned).
     * - Shipping costs may be returned except for the handling costs.
     * - Etc.
     *
     * This base implementation assumes that no manual lines are possible in a shop and
     * thus returns an empty array.
     *
     * @return array
     *   Description.
     *
     * @noinspection PhpUnused  called via
     *    {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getManualLineInfos(): array
    {
        return [];
    }

    /**
     * @noinspection PhpUnused  called via
     *   {@see \Siel\Acumulus\Collectors\CollectorManager::collectLinesForType()}
     */
    public function getVoucherLineInfos(): array
    {
        return [];
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
     */
    protected function callTypeSpecificMethod(string $method, ...$args): mixed
    {
        $method .= $this->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
