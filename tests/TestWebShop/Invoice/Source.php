<?php
namespace Siel\Acumulus\TestWebShop\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;

/**
 * Wraps a TestWebShop order or refund in an invoice source object.
 *
 * @todo:
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
 * - getPaymentMethod(): override if TestWebShop supports credit notes and stores
 *   a separate payment method for them.
 * - getPaymentMethodOrder(): implement if TestWebShop does not support credit
 *   notes or does not store a separate payment method for them.
 * - getPaymentStatus(): override or implement both getPaymentStatusOrder()
 *   and getPaymentStatusCreditNote().
 * - getPaymentDate(): override or implement both getPaymentDateOrder() and
 *   getPaymentDateCreditNote().
 * - getCountryCode(): implement.
 * - getCurrency(): implement.
 * - getAvailableTotals(): implement.
 * - setInvoice(): override if TestWebShop has separate invoice objects.
 * - getInvoiceReferenceOrder(): implement if TestWebShop has separate invoice
 *   numbers or references.
 * - getInvoiceDateOrder(): implement if TestWebShop has separate invoice dates.
 * - getShopOrder: override if TestWebShop supports credit notes.
 * - getShopCreditNotes(): override if TestWebShop supports credit notes.
 */
class Source extends BaseSource
{
    /**
     * {@inheritdoc}
     */
    protected function setSource()
    {
        // @todo: set the source, given an id (and type).
        if ($this->getType() === Source::Order) {
            $this->source = new Order($this->id);
        } else {
            $this->source = new CreditNote($this->id);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setId()
    {
        // @todo: set the id, given a loaded source.
        $this->id = $this->source->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        // @todo: override if TestWebShop assigns a separate reference number or string to its orders or credit notes, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    public function getDate()
    {
        // @todo: override or implement both getDateOrder() and getDateCreditNote()
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        // @todo: override or implement both getStatusOrder() and getStatusCreditNote()
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethod()
    {
        // @todo: override if TestWebShop supports credit notes and stores a separate payment method for them, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodOrder()
    {
        // @todo: implement if TestWebShop does not support credit notes or does not store a separate payment method for them, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus()
    {
        // @todo: override or implement both getPaymentStatusOrder() and getPaymentStatusCreditNote()
        // Assumption: credit slips are always in a paid status.
        if (($this->getType() === Source::Order && $this->source->hasBeenPaid()) || $this->getType() === Source::CreditNote) {
            $result = Api::PaymentStatus_Paid;
        } else {
            $result = Api::PaymentStatus_Due;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate()
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function getCountryCode()
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     *
     * TestWebShop stores the internal currency id, so look up the currency
     * object first then extract the ISO code for it.
     */
    public function getCurrency()
    {
        // @todo
        $result = array (
            Meta::Currency => $this->source->currency_code,
            Meta::CurrencyRate => (float) $this->source->conversion_rate,
            Meta::CurrencyDoConvert => true,
        );
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAvailableTotals()
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReferenceOrder()
    {
        // @todo: implement if TestWebShop has separate Invoice numbers and references,, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDateOrder()
    {
        // @todo: implement if TestWebShop has separate Invoice dates, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopOrderOrId()
    {
        // @todo: override if TestWebShop supports credit notes, otherwise remove.
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopCreditNotesOrIds()
    {
        // @todo: override if TestWebShop supports credit notes, otherwise remove.
    }
}
