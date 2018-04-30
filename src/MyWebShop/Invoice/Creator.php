<?php
namespace Siel\Acumulus\MyWebShop\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Creates a raw version of the Acumulus invoice based on a MyWebShop invoice
 * source.
 *
 * @todo:
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

    /** @var OrderRefund */
    protected $creditNote;

    /**
     * {@inheritdoc}
     */
    protected function setInvoiceSource($invoiceSource)
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

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        // @todo: add objects from your webshop as property source (for use in tokenized values)
        parent::setPropertySources();
        $this->propertySources['address_invoice'] = new Address($this->order->id_address_invoice);
        $this->propertySources['address_delivery'] = new Address($this->order->id_address_delivery);
        $this->propertySources['customer'] = new Customer($this->invoiceSource->getSource()->id_customer);
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        // @todo: override or implement both addItemLinesOrder() and addItemLinesCreditNote()
        $result = array();
        $lines = $this->invoiceSource->getSource()->getItemLines();
        foreach ($lines as $line) {
            $result[] = $this->getItemLine($line);
        }
        return $result;
    }

    /**
     * Returns 1 item line, be it for an order or credit slip.
     *
     * @todo: specify type of this parameter
     * @param $item
     *   An OrderDetail line.
     *
     * @return array
     */
    protected function getItemLine($item)
    {
        $result = array();

        // @todo: add property source(s) for this item line.
        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);
        $sign = $this->invoiceSource->getSign();

        // @todo: add other tags and available meta tags (* = required):
        // * quantity
        // * unitprice and/or unitpriceinc
        // * vat related tags:
        //     * vatrate and/or vatamount
        //     * vat range tags (call method getVatRangeTags()
        //     * vat rate source
        // - meta-unitprice-recalculate: if admin enters product prices inc vat
        //   (which is normal) then the product price ex vat may be imprecise.
        //   If the exact vat rate is not yet known, it may be recalculated in
        //   the Completor phase.
        // - line totals

        // @todo: remove property source(s) for this item line.
        $this->removePropertySource('item');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = array();

        $sign = $this->invoiceSource->getSign();

        // @todo: add all needed and available tags to the result.

        return $result;
    }

    /**
     * Looks up and returns vat rate meta data.
     *
     * @todo: add any necessary parameters, e.g. the product object
     *
     * @return array
     *   An array with keys
     *   - Meta::VatRateLookup
     *   - Meta::VatRateLookupLabel
     *   - Meta::VatRateLookupSource
     *   or an empty array.
     */
    protected function getVatRateLookupMetadata()
    {
        // @todo: implement if you can't provide exact vat rates, while you may
        //   have a way to look it up, e.g via the current Product object.
        //   As the actual rate may differ from the rate a the moment the order
        //   was placed,we call this lookup data, that only will be used by the
        //   Completor if all else fail.
    }
}
