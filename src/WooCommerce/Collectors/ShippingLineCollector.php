<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use WC_Tax;

use function count;
use function is_array;
use function is_string;
use function strlen;

/**
 * ShippingLineCollector contains WooCommerce specific {@see LineType::Shipping} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectShippingLine($acumulusObject, $propertySources);
    }

    /**
     * @param \Siel\Acumulus\Data\Line $line
     *   A shipping line with the mapped fields filled in.
     */
    protected function collectShippingLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \WC_Order_Item_Shipping $shippingItem */
        $shippingItem = $propertySources->get('shippingLineInfo');
        $line->metadataSet(Meta::Id, $shippingItem->get_id());

        $line->product = $shippingItem->get_name();
        $line->quantity = (float) $shippingItem->get_quantity();

        // Note: this info is WC3+ specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise. However, in the order item metadata, get_total() will be rounded to
        // the cent by WC. The VAT in get_total_tax() is also rounded to the cent. So, we
        // should ty to get more precise amounts:
        // - shipping ex: look up in the shipping method data.
        // - vat amount: look up in the get_taxes() data.
        $shippingEx = (float) $shippingItem->get_total();
        $shippingEx /= $line->quantity;
        $precisionShippingEx = 0.01;
        $shippingVat = ((float) $shippingItem->get_total_tax()) / $line->quantity;
        $precisionVat = 0.01;
        $shippingInc = $shippingEx + $shippingVat;

        // To avoid rounding errors, we try to get the more precise non-formatted amount
        // for $shippingEx. Due to changes in how WC configures shipping methods (now
        // based on zones), storage of order item metadata has changed. Therefore, we have
        // to try several option names.
        $methodId = $shippingItem->get_method_id();
        if (str_starts_with($methodId, 'legacy_')) {
            $methodId = substr($methodId, strlen('legacy_'));
        }
        // Instance id is the zone, will return an empty value if not present.
        $instanceId = $shippingItem->get_instance_id();
        $optionName = !empty($instanceId)
            ? "woocommerce_{$methodId}_{$instanceId}_settings"
            : "woocommerce_{$methodId}_settings";
        $option = get_option($optionName);

        if (!empty($option['cost'])) {
            // Note that "Cost" may contain a formula or use commas. Dutch help text: 'Vul
            // een bedrag(excl. btw) in of een berekening zoals 10.00 * [qty]. Gebruik
            // [qty] voor het aantal artikelen, [cost] voor de totale prijs van alle
            // artikelen, en [fee percent="10" max_fee=""min_fee="20"] voor prijzen
            // gebaseerd op percentage.'
            $cost = str_replace(',', '.', $option['cost']);
            if (is_numeric($cost)) {
                $cost = (float) $cost;
                if (Number::floatsAreEqual($cost, $shippingEx)) {
                    $shippingEx = $cost;
                    $precisionShippingEx = 0.001;
                }
            }
        }

        // Also to avoid rounding errors, we try to get a more precise vat amount. Besides
        // a "total_tax" order item metadata record, WC also stores a "taxes" metadata
        // record which contains the non-rounded tax amounts. For shipping this is
        // normally just 1 amount.
        $taxes = $shippingItem->get_taxes();
        $this->addShippingVatRateDataBasedOnTaxes($line, $taxes, $propertySources);

        $line->unitPrice = $shippingEx;
        $line->metadataSet(Meta::PrecisionUnitPrice, $precisionShippingEx);
        // Vat amount may already have been set in addShippingVatRateDataBasedOnTaxes().
        if (!$line->metadataExists(Meta::VatAmount)) {
            $line->metadataSet(Meta::VatAmount, $shippingVat);
            $line->metadataSet(Meta::PrecisionVatAmount, $precisionVat);
        }

        if ($line->metadataGet(Meta::PrecisionUnitPrice) < 0.009 && $line->metadataGet(Meta::PrecisionVatAmount) < 0.009) {
            // We have a more precise unit price and vat amount, but as this line will be
            // rounded in the end anyway, we should add the rounded inc price and
            // recalculate the unit price later ...
            $line->metadataSet(Meta::UnitPriceInc, $shippingInc);
            $line->metadataSet(Meta::PrecisionUnitPriceInc, 0.01);
            $line->metadataAdd(Meta::FieldsCalculated, Meta::UnitPriceInc);
            $line->metadataSet(Meta::RecalculatePrice, Fld::UnitPrice);
        }
    }

    /**
     * Looks up and returns vat rate (lookup) metadata for shipping lines.
     *
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if that is
     * possible for Dutch web shops, but if a shipping line does have multiple taxes we
     * fall back to the tax class setting for shipping methods.
     *
     * This method may add the following metadata:
     * - Meta::VatAmount
     * - Meta::VatClassId
     * - Meta::VatRateLookup (*)
     * - Meta::VatRateLookupLabel (*)
     * - Meta::VatRateLookupSource (*)
     *
     * @param array|array[]|null $taxes
     *   The taxes applied to a shipping line.
     */
    protected function addShippingVatRateDataBasedOnTaxes(Line $line, ?array $taxes, PropertySources $propertySources): void
    {
        $taxRateFound = false;
        if (is_array($taxes)) {
            // Since version ?.?, $taxes has an indirection by key 'total'.
            if (is_string(array_key_first($taxes))) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $taxes = current($taxes);
            }
            if (is_array($taxes)) {
                foreach ($taxes as $taxRateId => $amount) {
                    if (!empty($amount) && !Number::isZero($amount)) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId, OBJECT);
                        if ($taxRate) {
                            if (!$taxRateFound) {
                                $line->metadataSet(Meta::VatAmount, ((float) $amount) / $line->quantity);
                                $line->metadataSet(Meta::PrecisionVatAmount, 0.001);
                                $line->metadataAdd(Meta::VatRateLookup, null, true);
                                $line->metadataAdd(Meta::VatRateLookupLabel, null, true);
                                $line->metadataSet(
                                    Meta::VatClassId,
                                    $taxRate->tax_rate_class !== '' ? $taxRate->tax_rate_class : 'standard'
                                );
                                // Vat class name is the non-sanitized version of the id
                                // and thus does not convey more information: don't add.
                                $line->metadataSet(Meta::VatRateLookupSource, 'shipping line taxes');
                                $taxRateFound = true;
                            }
                            // get_rate_percent() contains a % at the end of the string:
                            // remove it.
                            $line->metadataAdd(Meta::VatRateLookup, substr(WC_Tax::get_rate_percent($taxRateId), 0, -1), true);
                            $line->metadataAdd(Meta::VatRateLookupLabel, WC_Tax::get_rate_label($taxRate), true);
                        }
                    }
                }
            }
        }

        if (!$taxRateFound) {
            // Apparently we have free shipping (or a misconfigured shipment method). Use
            // a fall-back: WooCommerce only knows 1 tax rate for all shipping methods,
            // stored in config:
            $shippingTaxClass = get_option('woocommerce_shipping_tax_class');
            if (is_string($shippingTaxClass)) {
                /** @var \Siel\Acumulus\Data\Invoice $invoice */
                $invoice = $propertySources->get('invoice');
                /** @var \Siel\Acumulus\WooCommerce\Invoice\Source $source */
                $source = $propertySources->get('source');
                /** @var \WC_Order $order */
                $order = $source->getOrder()->getShopObject();

                // Since WC3, the shipping tax class can be based on those from
                // the product items in the cart (which should be the preferred
                // value for this setting). The code to get the derived tax
                // class is more or less copied from WC_Abstract_Order.
                if ($shippingTaxClass === 'inherit') {
                    $foundClasses = array_intersect(array_merge([''], WC_Tax::get_tax_class_slugs()), $order->get_items_tax_classes());
                    $shippingTaxClass = count($foundClasses) === 1 ? reset($foundClasses) : false;
                }

                if (is_string($shippingTaxClass)) {
                    $this->addVatRateLookupMetadataByTaxClass($line, $shippingTaxClass, $invoice);
                    if (!empty($line->metadataGet(Meta::VatRateLookup))) {
                        $line->metadataSet(Meta::VatRateLookupSource, "get_option('woocommerce_shipping_tax_class')");
                    }
                }
            }
        }
    }
}
