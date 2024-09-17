<?php
namespace Siel\Acumulus\MyWebShop\Invoice;

use Siel\Acumulus\Invoice\Creator as BaseCreator;

/**
 * Creates a raw version of the Acumulus invoice based on a MyWebShop invoice
 * source.
 *
 * @todo: from the following list:
 * - for reasons of readability (shorter expressions) and autocomplete in your
 *   IDE, you might want to introduce properties for the webshop order and/or
 *   refund.
 * - setInvoiceSource(): set the properties from the previous line (if you
 *   defined so).
 * - addPropertySource(): add MyWebShop specific objects related to an order or
 *   refund (e.g. customer, invoice address)
 * - getItemLines: override or implement both getItemLinesOrder() and
 *   getItemLinesCreditNote()
 * - getItemLine: implement getItemLine() or both getItemLineOrder() and
 * getItemLineCreditNote()
 * - getShippingLines(): implement if MyWebShop supports multiple shipping lines.
 * - getShippingLine(): implement.
 * - getShippingMethodName(): implement.
 * - getGiftWrappingLine(): implement if supported by MyWebShop.
 * - getPaymentFeeLine(): implement if supported by MyWebShop.
 * - getDiscountLines(): override or implement both getDiscountLinesOrder() and
 * - getDiscountLinesCreditNote().
 * - getVatRateLookupMetadata(): implement if it can be useful. You may need
 *   various methods to get lookup data for product vat rates, shipping methods
 *   vat rates, etc.
 */
class Creator extends BaseCreator
{
    /** @var Order */
    protected $order;

    /** @var Refund */
    protected $creditNote;

    protected function setInvoiceSource(\Siel\Acumulus\Invoice\Source $invoiceSource): void
    {
        // @todo: add objects from your webshop as property source (for use in tokenized values)
        parent::setInvoiceSource($invoiceSource);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                $this->creditNote = $this->invoiceSource->getSource();
                $this->order = $this->invoiceSource->getOrder()->getSource();
                break;
        }
    }

    protected function setPropertySources(): void
    {
        // @todo: add objects from your webshop as property source (for use in tokenized values)
        parent::setPropertySources();
        $this->propertySources['address_invoice'] = new Address($this->order->id_address_invoice);
        $this->propertySources['address_delivery'] = new Address($this->order->id_address_delivery);
        $this->propertySources['customer'] = new Customer($this->invoiceSource->getSource()->id_customer);
    }

    protected function getShippingLine(): array
    {
        $result = [];

        $sign = $this->invoiceSource->getSign();

        // @todo: add all needed and available tags to the result.

        return $result;
    }

    /**
     * Looks up and returns vat rate metadata.
     *
     * @todo: add any necessary parameters, e.g. the product object
     *
     * @return array
     *   An array with keys
     *   - Meta::VatClassId: int|string
     *   - Meta::VatClassName: string
     *   - Meta::VatRateLookup: float|float[]
     *   - Meta::VatRateLookupLabel: string|string[]
     *   - Meta::VatRateLookupSource: string, free text indicating where you
     *     found the other info. Fill if you use multiple sources to get the
     *     other info. SO this is debug info that tells you which code path was
     *     followed.
     *   Keys are optional, but the more keys you can fill, the better.
     */
    protected function getVatRateLookupMetadata(): array
    {
        // @todo: implement if you can't provide exact vat rates, while you may
        //   have a way to look it up, e.g via the current Product object.
        //   As the actual rate may differ from the rate a the moment the order
        //   was placed,we call this lookup data, that only will be used by the
        //   Completor if all else fail.
    }
}
