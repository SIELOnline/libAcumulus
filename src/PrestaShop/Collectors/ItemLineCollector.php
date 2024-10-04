<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

/**
 * ItemLineCollector contains PrestaShop specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * This PrestaShop override collects:
     * -
     * - ...
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getItemLine($acumulusObject, $propertySources);
    }

    /**
     * Collects 1 item line, both for an order or credit slip.
     *
     * @legacy: This is a copy of old Creator code: to be integrated in this collector in
     *   a neat/correct way!
     *
     * @param Line $line
     *   An item line with the mapped fields filled in
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $propertySources->get('invoice');
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $propertySources->get('source');
        $sign = $source->getSign();
        /** @var \Siel\Acumulus\Invoice\Item $item */
        $item = $propertySources->get('item');
        /** @var array $shopItem */
        $shopItem = $item->getShopObject();

        // 'unit_amount' (table order_detail_tax) is not always set: assume
        // no discount if not set, so not necessary to add the value.
        if (isset($shopItem['unit_amount']) &&
            !Number::floatsAreEqual($shopItem['unit_amount'], $line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice)
        ) {
            $line->metadataSet(
                Meta::LineDiscountVatAmount,
                $shopItem['unit_amount'] - ($line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice)
            );
        }

        // Try to get the vat rate:
        // The field 'rate' comes from order->getOrderDetailTaxes() and is thus only
        // available for orders and was not filled before PS1.6.1.1. So, check if the
        // field is available.
        // The fields 'unit_amount' and 'total_amount' (also from table order_detail_tax)
        // are based on the discounted product price and thus cannot be used to get the
        // vat rate.
        if (isset($shopItem['rate'])) {
            $line->vatRate = $shopItem['rate'];
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
        } else {
            $line->unitPrice = $sign * $shopItem['unit_price_tax_excl'];
            $line->metadataSet(Meta::VatAmount, $sign * ($shopItem['unit_price_tax_incl'] - $shopItem['unit_price_tax_excl']));
            $line->metadataSet(Meta::PrecisionUnitPrice, $this->precision);
            $line->metadataSet(Meta::PrecisionVatAmount, $this->precision);
        }
        $taxRulesGroupId = isset($shopItem['id_tax_rules_group']) ? (int) $shopItem['id_tax_rules_group'] : 0;
        // VAT lookup metadata should be based on the address used for VAT calculations.
        /** @noinspection NullPointerExceptionInspection */
        $vatBasedOn = $invoice->getCustomer()->getMainAddressType();
        $addressId = $vatBasedOn === AddressType::Invoice
            ? $source->getOrder()->getShopObject()->id_address_invoice
            : $source->getOrder()->getShopObject()->id_address_delivery;
        $this->addVatRateLookupMetadata($line, $addressId, $taxRulesGroupId);

        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
    }
}
