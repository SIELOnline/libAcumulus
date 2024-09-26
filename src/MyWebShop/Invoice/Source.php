<?php
namespace Siel\Acumulus\MyWebShop\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Meta;

/**
 * Wraps a MyWebShop order or refund in an invoice source object.
 *
 *  @todo: from the following list:
 * - for reasons of autocomplete in your IDE, you might want to override the
 *   $source property and define its possible type(s) (using @var).
 * - setId(): override or implement both setIdOrder() and setIdCreditNote().
 * - setSource(): override or implement both setSourceOrder() and
 *   setSourceCreditNote().
 * - getReference(): override when this is not the internal id.
 * - getDate(): override or implement both getDateOrder() and
 *   getDateCreditNote().
 * - getStatus(): override or implement both getStatusOrder() and
 *   getStatusCreditNote().
 * - getPaymentMethod(): override if MyWebShop supports credit notes and stores
 *   a separate payment method for them.
 * - getPaymentMethodOrder(): implement if MyWebShop does not support credit
 *   notes or does not store a separate payment method for them.
 * - getPaymentStatus(): override or implement both getPaymentStatusOrder()
 *   and getPaymentStatusCreditNote().
 * - getPaymentDate(): override or implement both getPaymentDateOrder() and
 *   getPaymentDateCreditNote().
 * - getCountryCode(): implement.
 * - getCurrency(): implement.
 * - getAvailableTotals(): implement.
 * - setInvoice(): override if MyWebShop has separate invoice objects.
 * - getInvoiceReferenceOrder(): implement if MyWebShop has separate invoice
 *   numbers or references.
 * - getInvoiceDateOrder(): implement if MyWebShop has separate invoice dates.
 * - getShopOrder: override if MyWebShop supports credit notes.
 * - getShopCreditNotes(): override if MyWebShop supports credit notes.
 */
class Source extends BaseSource
{
    protected function setShopObject(): void
    {
        // @todo: set the source, given an id (and type).
        if ($this->getType() === Source::Order) {
            $this->shopObject = new Order($this->id);
        } else {
            $this->shopObject = new CreditNote($this->id);
        }
    }

    protected function setId(): void
    {
        // @todo: set the id, given a loaded source.
        $this->id = (int) $this->getShopObject()->id;
    }

    public function getReference(): string|int
    {
        // @todo: override if MyWebShop assigns a separate reference number or string to its orders or credit notes, otherwise remove.
    }

    public function getDate(): string
    {
        // @todo: override or implement both getDateOrder() and getDateCreditNote()
    }

    public function getStatus(): int|string|null
    {
        // @todo: override or implement both getStatusOrder() and getStatusCreditNote()
    }

    public function getPaymentMethod(): int|string|null
    {
        // @todo: override if MyWebShop supports credit notes and stores a separate payment method for them, otherwise remove.
    }

    protected function getPaymentMethodOrder(): int|string|null
    {
        // @todo: implement if MyWebShop does not support credit notes or does not store a separate payment method for them, otherwise remove.
    }

    public function getPaymentStatus(): int
    {
        // @todo: override or implement both getPaymentStatusOrder() and getPaymentStatusCreditNote()
        // Assumption: credit slips are always in a paid status.
        if (($this->getType() === Source::Order && $this->getShopObject()->hasBeenPaid()) || $this->getType() === Source::CreditNote) {
            $result = Api::PaymentStatus_Paid;
        } else {
            $result = Api::PaymentStatus_Due;
        }
        return $result;
    }

    public function getPaymentDate(): ?string
    {
        // @todo: provide implementation.
    }

    public function getCountryCode(): string
    {
        // @todo: provide implementation.
    }

    /**
     * {@inheritdoc}
     *
     * MyWebShop stores the internal currency id, so look up the currency
     * object first then extract the ISO code for it.
     */
    public function getCurrency(): Currency
    {
        // @todo: provide implementation.
        return new Currency($this->getShopObject()->currency_code, (float) $this->getShopObject()->conversion_rate, true);
    }

    public function getTotals(): Totals
    {
        // @todo: provide implementation.
    }

    protected function getInvoiceReferenceOrder(): string|int|null
    {
        // @todo: implement if MyWebShop has separate Invoice numbers and references,, otherwise remove.
    }

    protected function getInvoiceDateOrder(): ?string
    {
        // @todo: implement if MyWebShop has separate Invoice dates, otherwise remove.
    }

    protected function getShopOrderOrId(): object|array|int
    {
        // @todo: override if MyWebShop supports credit notes, otherwise remove.
    }

    protected function getShopCreditNotesOrIds(): iterable
    {
        // @todo: override if MyWebShop supports credit notes, otherwise remove.
    }

    protected function createItems(): array
    {
        // @todo: implement
    }
}
