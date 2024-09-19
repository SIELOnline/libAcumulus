<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

use function count;
use function is_array;
use function is_object;

/**
 * ItemLineCollector contains HikaShop specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectItemLine($acumulusObject);
    }

    /**
     * Collects the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function collectItemLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getPropertySource('invoice');
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();
        /** @var \Siel\Acumulus\Joomla\HikaShop\Invoice\Item $item */
        $item = $this->getPropertySource('item');
        $shopItem = $item->getShopObject();

        // Remove html with variant info from product name, we'll add that later
        // using children lines.
        if (isset($line->product) && ($pos = strpos($line->product, '<span')) !== false) {
            $line->product = substr($line->product, 0, $pos);
        }

        $productPriceEx = (float) $shopItem->order_product_price;
        $productVat = (float) $shopItem->order_product_tax;

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $line->unitPrice = $productPriceEx + $productVat;
        } else {
            $line->unitPrice = $productPriceEx;
            $line->metadataSet(Meta::LineAmount, $shopItem->order_product_total_price_no_vat);
            $line->metadataSet(Meta::LineAmountInc, $shopItem->order_product_total_price);
        }

        // Try to get the exact vat rate from the order-product info.
        // Note that this info remains correct when rates are changed as this
        // info is stored upon order creation in the order_product table.
        if (is_array($shopItem->order_product_tax_info)) {
            if (count($shopItem->order_product_tax_info) === 1) {
                $productVatInfo = reset($shopItem->order_product_tax_info);
                if (isset($productVatInfo->tax_rate)) {
                    $vatRate = $productVatInfo->tax_rate;
                }
            } elseif (count($shopItem->order_product_tax_info) === 0) {
                $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
            } else {
                $this->addWarning($line, 'Cumulative vat rates applied: unknown in NL');
            }
        }

        if (isset($vatRate)) {
            $line->vatRate = 100.0 * $vatRate;
            $line->metadataSet(Meta::VatAmount, $productVat);
            $line->metadataSet(Meta::VatRateSource, Number::isZero($productVat) ? VatRateSource::Exact0 : VatRateSource::Exact);
        } else {
            $this->addVatRangeTags($line, $productVat, $productPriceEx, $this->precision, $this->precision);
        }

        // Add vat class meta data.
        if (isset($productVatInfo->category_namekey)) {
            $line->metadataSet(Meta::VatClassId, $productVatInfo->category_namekey);
            /** @var \hikashopCategoryClass $categoryClass */
            $categoryClass = hikashop_get('class.category');
            $categoryClass->namekeys = ['category_namekey'];
            /** @var \stdClass $category */
            $category = $categoryClass->get($productVatInfo->category_namekey);
            if (isset($category->category_name)) {
                $line->metadataSet(Meta::VatClassName, $category->category_name);
            }

            // Add vat rate metadata.
            // We can use hikashopCurrencyClass::getTax() to get a tax rate.
            // This method wants:
            // - The zone - state or country - where the customer lives.
            // - The customer type: we should use 'individual' to prevent
            //   getting 0% (vat exempt) when the customer is a
            //  'company_with_vat_number'.
            // - The category id of the tax class, which we have in $category.
            if (isset($category->category_id)) {
                if ($invoice->getCustomer()->getMainAddressType() === AddressType::Shipping) {
                    $address1 = $order->shipping_address;
                    $address2 = $order->billing_address;
                } else {
                    $address1 = $order->billing_address;
                    $address2 = $order->shipping_address;
                }
                $zone_name = !empty($address1->address_state_orig) ? $address1->address_state_orig : $address1->address_country_orig;
                if (empty($zone_name)) {
                    $zone_name = !empty($address2->address_state_orig) ? $address2->address_state_orig : $address2->address_country_orig;
                }
                if (!empty($zone_name)) {
                    /** @var \hikashopZoneClass $zoneClass */
                    $zoneClass = hikashop_get('class.zone');
                    $zone = $zoneClass->get($zone_name);
                    if (!empty($zone->zone_id)) {
                        // We have a zone for the customer. Get the vat rate for
                        // a normal customer, even if this is a company, so we
                        // do not get the "vat exempt" rate.
                        /** @var \hikashopCurrencyClass $currencyClass */
                        $currencyClass = hikashop_get('class.currency');
                        $vatRate = $currencyClass->getTax($zone->zone_id, $category->category_id, 'individual');
                        $line->metadataSet(Meta::VatRateLookup, (float) $vatRate * 100);
                    }
                }
            }
        } elseif (is_array($shopItem->order_product_tax_info) && count($shopItem->order_product_tax_info) === 0) {
            // We do not have any order_product_vat_info at all: the product
            // does not have any tax category assigned.
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        }

        // Add variant info.
        if (!empty($shopItem->order_product_options)) {
            $this->addVariantLines($line, $shopItem);
        }
    }

    /**
     * Adds child lines that describes this variant.
     *
     * @param object $item
     *   See {@see \hikashopOrder_productClass}
     */
    protected function addVariantLines(Line $line, object $item): void
    {
        foreach ($item->order_product_options as $key => $value) {
            // Add variant.
            /** @var Line $child */
            $child = $this->createAcumulusObject();
            $child->unitPrice = 0;
            $child->quantity = $line->quantity;
            $child->metadataSet(Meta::VatAmount, 0);
            $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
            if (is_object($value)) {
                /** @var \hikashopCharacteristicClass $characteristicClass */
                $characteristicClass = hikashop_get('class.characteristic');
                $characteristicId = $value->characteristic_parent_id;
                $characteristicRecord = $characteristicClass->get($characteristicId);
                $characteristic = $characteristicRecord?->characteristic_value ?? $characteristicId;

                // Normally, the chosen variant is stored in the database with the order
                // product. If not we look up its current value via the variant id.
                if (!empty($value->characteristic_value)) {
                    $chosenOption = $value->characteristic_value;
                } else {
                    $chosenOption = $value->variant_characteristic_id;
                    if (!empty($chosenOption)) {
                        $line->metadataSet(Meta::Id, $chosenOption);
                        $optionRecord = $characteristicClass->get($chosenOption);
                        $chosenOption = $optionRecord->characteristic_value;
                    }
                }
            } else {
                $characteristic = $key;
                $chosenOption = (string) $value;
            }

            $child->product = "$characteristic: $chosenOption";
            $line->addChild($child);
        }
    }
}
