<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Address;
use Exception;
use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use TaxManagerFactory;
use TaxRulesGroup;

/**
 * LineCollector contains the PrestaShop specific logic to collect an item line.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Precision: 1 of the amounts, probably the prince incl tax, is entered by
     * the admin and can thus be considered exact. The other is calculated by
     * the system and not rounded and can thus be considered to have a precision
     * better than 0.0001.
     *
     * However, we have had a support call where the precision, for a credit
     * note, turned out to be only 0.002. This was, apparently, with a price
     * entered excl. vat: 34,22; incl: 41,40378; (computed) vat: 7,18378.
     * The max-vat rate was just below 21%, so no match was made.
     */
    protected float $precision = 0.01;

    /**
     * This PrestaShop override collects:
     * -
     * - ...
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $this->getItemLine($acumulusObject);
    }

    /**
     * Collects 1 item line, both for an order or credit slip.
     *
     * @legacy: This is a copy of old Creator code: to be integrated in this collector in
     *   a neat/correct way!
     *
     * @param Line $line
     *   An item line with the mapped and logic fields filled in
     */
    protected function getItemLine(Line $line): void
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getPropertySource('invoice');
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $sign = $source->getSign();
        /** @var \Siel\Acumulus\Invoice\Item $item */
        $item = $this->getPropertySource('item');
        /** @var array $shopItem */
        $shopItem = $item->getShopObject();

        // Check for cost price and margin scheme.
        if (!empty($line->costPrice) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $line->unitPrice = $sign * $shopItem['unit_price_tax_incl'];
        } else {
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
        }

        // Try to get the vat rate:
        // The field 'rate' comes from order->getOrderDetailTaxes() and is thus
        // only available for orders and was not filled before PS1.6.1.1. So,
        // check if the field is available.
        // The fields 'unit_amount' and 'total_amount' (also from table
        // order_detail_tax) are based on the discounted product price and thus
        // cannot be used to get the vat rate.
        if (isset($shopItem['rate'])) {
            $line->vatRate = $shopItem['rate'];
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
        } else {
            static::addVatRangeTags(
                $line,
                $sign * ($shopItem['unit_price_tax_incl'] - $shopItem['unit_price_tax_excl']),
                $sign * $shopItem['unit_price_tax_excl'],
                $this->precision,
                $this->precision
            );
        }
        $taxRulesGroupId = isset($shopItem['id_tax_rules_group']) ? (int) $shopItem['id_tax_rules_group'] : 0;
        // VAT lookup metadata should be based on the address used.
        /** @noinspection NullPointerExceptionInspection */
        $vatBasedOn = $invoice->getCustomer()->getMainAddressType();
        $addressId = $vatBasedOn === AddressType::Invoice ? $source->getOrder()->getSource()->id_address_invoice :
            $source->getOrder()->getSource()->id_address_delivery;
        $this->addVatRateLookupMetadata($line, $addressId, $taxRulesGroupId);

        /** @noinspection UnsupportedStringOffsetOperationsInspection */
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
    }

    /**
     * Looks up and returns vat rate metadata.
     *
     * The following metadata keys might be set:
     * - Meta::VatClassId: int
     * - Meta::VatClassName: string
     * - Meta::VatRateLookup: float
     * - Meta::VatRateLookupLabel: string
     */
    protected function addVatRateLookupMetadata(Line $line, int $addressId, int $taxRulesGroupId): void
    {
        try {
            if (!empty($taxRulesGroupId)) {
                $taxRulesGroup = new TaxRulesGroup($taxRulesGroupId);
                $address = new Address($addressId);
                $taxManager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
                $taxCalculator = $taxManager->getTaxCalculator();
                $line->metadataSet(Meta::VatClassId, $taxRulesGroup->id);
                $line->metadataSet(Meta::VatClassName, $taxRulesGroup->name);
                $line->metadataSet(Meta::VatRateLookup, $taxCalculator->getTotalRate());
                $line->metadataSet(Meta::VatRateLookupLabel, $taxCalculator->getTaxesName());
            } else {
                $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
            }
        } catch (Exception) {
        }
    }
}
