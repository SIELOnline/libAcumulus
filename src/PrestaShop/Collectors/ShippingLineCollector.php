<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Carrier;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * ShippingLineCollector contains PrestaShop specific {@see LineType::Shipping} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->getShippingLine($acumulusObject);
    }

    /**
     * Collects 1 shipping line, both for an order or credit slip.
     *
     * @param Line $line
     *   A shipping line with the mapped fields filled in
     */
    protected function getShippingLine(Line $line): void
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getPropertySource('invoice');
        /** @var \Siel\Acumulus\PrestaShop\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $sign = $source->getSign();

        $carrier = new Carrier($source->getOrder()->getShopObject()->id_carrier);
        // total_shipping_tax_excl is not very precise (rounded to the cent) and
        // often leads to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nicely rounded amount incl tax). So we
        // recalculate this ourselves.
        $vatRate = $source->getOrder()->getShopObject()->carrier_tax_rate;
        $shippingInc = $sign * $source->getShopObject()->total_shipping_tax_incl;
        $shippingEx = $shippingInc / (100 + $vatRate) * 100;
        $shippingVat = $shippingInc - $shippingEx;

        $line->product = !empty($carrier->name) ? $carrier->name : $this->t('shipping_costs');
        $line->unitPrice = $shippingInc / (100 + $vatRate) * 100;
        $line->metadataSet(Meta::UnitPriceInc, $shippingInc);
        $line->quantity = 1;
        $line->vatRate = $vatRate;
        $line->metadataSet(Meta::VatAmount, $shippingVat);
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
        $line->metadataAdd(Meta::FieldsCalculated, Tag::UnitPrice);
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
        // VAT lookup metadata should be based on the address used for VAT calculations.
        /** @noinspection NullPointerExceptionInspection */
        $vatBasedOn = $invoice->getCustomer()->getMainAddressType();
        $addressId = $vatBasedOn === AddressType::Invoice
            ? $source->getOrder()->getSource()->id_address_invoice
            : $source->getOrder()->getSource()->id_address_delivery;
        $this->addVatRateLookupMetadata($line, $addressId, $carrier->getIdTaxRulesGroup());
    }
}
