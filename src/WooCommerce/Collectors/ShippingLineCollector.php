<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use WC_Tax;

use function count;
use function is_array;
use function is_string;
use function strlen;

/**
 * ItemLineCollector contains WooCommerce specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectShippingLine($acumulusObject);
    }

    protected function collectShippingLine(Line $line): void
    {
        /** @var \WC_Order_Item_Shipping $shippingItem */
        $shippingItem = $this->getPropertySource('shippingItem');
        $taxes = $shippingItem->get_taxes();
        $this->addShippingVatRateLookupMetadata($line, $taxes);

        // Note: this info is WC3+ specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        $shippingEx = (float) $shippingItem->get_total();
        $precisionShippingEx = 0.01;

        // To avoid rounding errors, we try to get the non-formatted amount.
        // Due to changes in how WC configures shipping methods (now based on
        // zones), storage of order item metadata has changed. Therefore, we
        // have to try several option names.
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
            // Note that "Cost" may contain a formula or use commas: 'Vul een bedrag(excl.
            // btw) in of een berekening zoals 10.00 * [qty]. Gebruik [qty] voor het
            // aantal artikelen, [cost] voor de totale prijs van alle artikelen, en
            // [fee percent="10" min_fee="20" max_fee=""] voor prijzen gebaseerd op
            // percentage.'
            $cost = str_replace(',', '.', $option['cost']);
            if (is_numeric($cost)) {
                $cost = (float) $cost;
                if (Number::floatsAreEqual($cost, $shippingEx)) {
                    $shippingEx = $cost;
                    $precisionShippingEx = 0.001;
                }
            }
        }
        $quantity = $shippingItem->get_quantity();
        $shippingEx /= $quantity;
        $shippingVat = $shippingItem->get_total_tax() / $quantity;
        $precisionVat = 0.01;

        $line->product = $shippingItem->get_name();
        $line->unitPrice = $shippingEx;
        $line->quantity = $quantity;
        $line->metadataSet(Meta::Id, $shippingItem->get_id());
        self::addVatRangeTags($line, $shippingVat, $shippingEx, $precisionVat, $precisionShippingEx);
    }

    /**
     * Looks up and returns vat rate metadata for shipping lines.
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if
     * that is possible for Dutch web shops, but if a shipping line does have
     * multiple taxes we fall back to the tax class setting for shipping
     * methods, that can have multiple tax rates itself (@param array|array[]|null $taxes
     *   The taxes applied to a shipping line.
     *
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatRateLookup (*)
     *   - Meta::VatRateLookupLabel (*)
     *   - Meta::VatRateLookupSource (*)
     * @see
     * getVatRateLookupMetadataByTaxClass()). Anyway, this method will only
     * return metadata if only 1 rate was found.
     */
    protected function addShippingVatRateLookupMetadata(Line $line, ?array $taxes): void
    {
        $taxRateFound = false;
        if (is_array($taxes)) {
            // Since version ?.?, $taxes has an indirection by key 'total'.
            if (is_string(key($taxes))) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $taxes = current($taxes);
            }
            if (is_array($taxes)) {
                foreach ($taxes as $taxRateId => $amount) {
                    if (!empty($amount) && !Number::isZero($amount)) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId, OBJECT);
                        if ($taxRate) {
                            if (!$taxRateFound) {
                                $line->metadataAdd(Meta::VatRateLookup, null,true);
                                $line->metadataAdd(Meta::VatRateLookupLabel, null, true);
                                $line->metadataSet(Meta::VatClassId, $taxRate->tax_rate_class !== '' ? $taxRate->tax_rate_class : 'standard');
                                // Vat class name is the non-sanitized version of the id
                                // and thus does not convey more information: don't add.
                                $line->metadataSet(Meta::VatRateLookupSource, 'shipping line taxes');
                                $taxRateFound = true;
                            }
                            // get_rate_percent() contains a % at the end of the
                            // string: remove it.
                            $line->metadataAdd(Meta::VatRateLookup, substr(WC_Tax::get_rate_percent($taxRateId), 0, -1),true);
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
                $invoice = $this->getPropertySource('invoice');
                /** @var \Siel\Acumulus\WooCommerce\Invoice\Source $source */
                $source = $this->getPropertySource('source');
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
