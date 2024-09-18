<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\LineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

use function count;
use function func_num_args;

/**
 * ShippingLineCollector contains HikaShop specific {@see LineType::Shipping} collecting logic.
 *
 * @noinspection LongLine
 *
 * Note: HS 4+ has a setting on shipping methods "Automatic taxes"/"Automatische
 * belastingen" that determines how the vat on shipping is calculated. It is stored in
 * shipping_params->shipping_tax and can have the following values:
 * 0 - No/Nee: use the specified "Product tax category"/"Product btw categorie" to get the
 *   vat rate.
 * 1 - Proportion/Verhouding: use the weighed average vat rate on the item lines as vat
 *   rate for the shipping. This results in multiple shipping lines per used shipping
 *   method if different vat rates were applied on the item lines.
 * 2 - Highest rate/Hoogste waarde: use the highest vat rate from the item lines as vat
 *   rate for the shipping.
 * 3 - Lowest rate/Laagste waarde: use the lowest vat rate from the item lines as vat rate
 *   for the shipping.
 *
 * Real-life examples of order->order_shipping_params:
 * - {"prices":{"27@0":{"price_with_tax":"20.00000","tax":0}}}
 * - {"prices":{"30@0":{"price_with_tax":8,"tax":0,"taxes":{"EUICL":0}}}}
 * - {"prices":{"6@0":{"price_with_tax":4.000018,"tax":0.694218,"taxes":{"BTW":0.694218}}}}
 * - {"prices":{"5@0":{"price_with_tax":4.6177850629697,"tax":0.48558506296965,"taxes":{"BTW Laag":0.11063016598247,"BTW 0%":0,"BTW":0.37495489698718}}}}
 * - {"prices":{"5@0":{"price_with_tax":4.617861669234271,"tax":0.485661669234271,"taxes":{"BTW":0.485661669234271}}}}
 *
 * Explanation:
 * - stdClass|null order->order_shipping_params: may only be null if no or free shipping.
 *   Has 1 property:
 * - stdClass[] prices: with 1 entry per shipment - so normally 1 entry -, keyed like
 *   {shipping_method_id}@{index}. Each entry is a stdClass with properties:
 * - float price_with_tax: price including tax for this shipment
 * - float tax: (total) tax for this shipment
 * - (optional) float[] taxes: array of tax amounts keyed by tax class name. If a shipping
 *   method has proportional tax rates (i.e. following the contents of the cart), this
 *   array contains the proportion of the (total) tax per tax class (but thus no line for
 *   a vat free product without tax class), see last example.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectShippingLine($acumulusObject);
    }

    /**
     * Collects the shipping line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A\ shipping line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectShippingLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();
        $keyedShippingInfo = $this->getPropertySource('shippingInfo');
        $shippingInfo = reset($keyedShippingInfo);
        $key = key($keyedShippingInfo);

        if ($shippingInfo === null) {
            $this->getFreeShippingLine($line);
        } elseif ($key === Source::Order) {
            $this->getOrderLevelShippingLine($line, $order);
        } else {
            /** @noinspection PhpParamsInspection  reset did NOT return false */
            $this->getOrderShippingParamLevelShippingLine($line, $order, $key, $shippingInfo);
        }
    }

    protected function getFreeShippingLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');

        if ($source->getType() === Source::CreditNote) {
            // Free (or no) shipping: do not add on a credit note.
            $line->metadataSet(Meta::DoNotAdd, true);
        } else {
            // @nth: can we distinguish between free shipping and in-store pickup?
            $line->product = $this->t('free_shipping');
            $line->quantity = 1;
            $line->unitPrice = 0.0;
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        }
    }

    /**
     * @param object $order
     *   A {@see \stdClass} holding a hikashop_order table record.
     */
    protected function getOrderLevelShippingLine(Line $line, object $order): void
    {
        // If the property order_shipping_params is "empty" (no info to
        // extract from), we use the order_shipping_* properties at the
        // order level.
        $line->product = $this->getShippingMethodName($order->order_shipping_id);
        $line->quantity = 1;
        $line->metadataSet(Meta::UnitPriceInc, $order->order_shipping_price);
        $line->metadataSet(Meta::VatAmount, $order->order_shipping_tax);
        $line->vatRate = null;
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
    }

    /**
     * @param object $order
     *   A {@see \stdClass} holding a hikashop_order table record.
     * @param string $key
     *   {shipping_method_id}@{index}.
     * @param object $shippingInfo
     *   See class documentation.
     */
    protected function getOrderShippingParamLevelShippingLine(Line $line, object $order, string $key, object $shippingInfo): void
    {
        // For each shipment we are going to add 1 or more shipping lines.
        /** @var \hikashopTaxClass $taxClassManager */
        $taxClassManager = hikashop_get('class.tax');

        $shippingAmountIncTotal = 0.0;
        $shippingVatTotal = 0.0;
        $warningAdded = false;

        [$shipping_id, $index] = explode('@', $key);
        $index = (int) $index;
        $product = $this->getShippingMethodName($shipping_id);
        $quantity = 1;
        if ($index > 0) {
            $product .= sprintf(' %d', $index + 1);
        }

        if (empty($shippingInfo->taxes)) {
            // Empty or no tax breakdown, probably because there's no tax.
            $line->product = $product;
            $line->quantity = $quantity;
            $line->metadataSet(Meta::UnitPriceInc, $shippingInfo->price_with_tax);
            $line->metadataSet(Meta::VatAmount, $shippingInfo->tax);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
            $shippingAmountIncTotal += $shippingInfo->price_with_tax;
            $shippingVatTotal += $shippingInfo->tax;
        } else {
            // Detailed tax breakdown is available: add a line per vat rate.
            $lines = [];
            $shippingMethodAmountIncTotal = 0.0;
            $addMissingAmountIndex = null;
            $isFirst = true;
            foreach ($shippingInfo->taxes as $taxNameKey => $shippingVat) {
                $shippingLine = $isFirst ? $line : $this->getContainer()->createAcumulusObject(DataType::Line);
                $shippingLine->metadataSet(Meta::SubType, LineType::Shipping);
                $isFirst = false;
                $taxClass = $taxClassManager->get($taxNameKey);
                $vatRate = $taxClass ? (float) $taxClass->tax_rate : null;
                if ($vatRate !== null && !Number::isZero($vatRate)) {
                    $shippingEx = $shippingVat / $vatRate;
                } else {
                    // Either $vatRate = null or $vatRate = 0.0: in both cases we cannot
                    // compute the price ex, so we fill in null or 0.0 for now and will
                    // fill it with the missing amount at the end of this loop.
                    $shippingEx = $vatRate;
                    $addMissingAmountIndex = count($lines);
                }
                $shippingLine->product = "$product ($taxNameKey)";
                $shippingLine->quantity = $quantity;
                $shippingLine->unitPrice = $shippingEx;
                $shippingLine->metadataSet(Meta::VatAmount, $shippingVat);

                if ($taxClass !== null) {
                    $shippingLine->vatRate = 100.0 * $vatRate;
                    $shippingLine->metadataSet(Meta::VatRateSource, VatRateSource::Creator_Lookup);
                    $shippingLine->metadataSet(Meta::VatClassName, $taxNameKey);
                    $shippingLine->metadataSet(Meta::VatRateLookup, 100.0 * $vatRate);
                } else {
                    $shippingLine->vatRate = null;
                    $shippingLine->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
                    $shippingLine->metadataSet(Meta::Warning, "Tax class '$taxNameKey' does no longer exist");
                }
                $lines[] = $shippingLine;
                $shippingMethodAmountIncTotal += $shippingEx + $shippingVat;
            }
            // Fill in the missing amount if we had a 0 rate.
            if ($addMissingAmountIndex !== null) {
                $lines[$addMissingAmountIndex]->unitPrice = $shippingInfo->price_with_tax - $shippingMethodAmountIncTotal;
                $shippingMethodAmountIncTotal += $lines[$addMissingAmountIndex]->unitPrice;
            }
            if (!Number::floatsAreEqual($shippingMethodAmountIncTotal, $shippingInfo->price_with_tax)) {
                // @todo: fill in the missing amount if we had a vat free
                //   rate (indicated so by the product having no tax class).
                $shippingLine = $this->getContainer()->createAcumulusObject(DataType::Line);
                $shippingLine->metadataSet(Meta::SubType, LineType::Shipping);
                $shippingLine->product = "$product (?)";
                $shippingLine->quantity = $quantity;
                $shippingLine->unitPrice = $shippingInfo->price_with_tax - $shippingMethodAmountIncTotal;
                $shippingLine->vatRate = 0;
                $shippingLine->metadataSet(Meta::VatAmount, 0.0);
                $shippingLine->metadataSet(Meta::VatRateSource, VatRateSource::Creator_Missing_Amount);
                $shippingLine->metadataSet(Meta::VatClassName, Config::VatClass_Null);
                $shippingLine->addWarning(
                    'Amounts for this shipping method do not add up: '
                    . 'probably vat free product or rates have changed. (order_shipping_params->prices = '
                    . str_replace('"', "'", json_encode($order->order_shipping_params->prices, Meta::JsonFlags))
                    . ')'
                );
                $warningAdded = true;
                $lines[] = $shippingLine;
            }
            $shippingAmountIncTotal += $shippingInfo->price_with_tax;
            $shippingVatTotal += $shippingInfo->tax;
            // We can only "return" the line passed in: add other lines as children of
            // that one.
            for ($i = 1, $c = count($lines); $i < $c; $i++) {
                $line->addChild($lines[$i]);
            }
        }
        if (!Number::floatsAreEqual($shippingAmountIncTotal, $order->order_shipping_price)
            || !Number::floatsAreEqual($shippingVatTotal, $order->order_shipping_tax)) {
            // Problem: lost too much precision? (or we had a rate that has
            // changed: we will already have discovered that above, so we do
            // not produce this warning here.)
            if (!$warningAdded) {
                $line->addWarning(
                    'Amounts for the shipping method(s) do not add up: lost too much precision?'
                    . ' (order_shipping_params->prices = '
                    . str_replace('"', "'", json_encode($order->order_shipping_params->prices, Meta::JsonFlags))
                    . ')'
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override may get 1 parameter: a shipping_id, identifying a shipping method.
     */
    protected function getShippingMethodName(): string
    {
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();

        $shipping_id = func_num_args() > 0 ? func_get_arg(0) : $order->order_shipping_id;

        /** @var \hikashopShippingClass $class */
        $class = hikashop_get('class.shipping');
        $shipping = $class->get($shipping_id);
        if (!empty($shipping->shipping_name)) {
            return $shipping->shipping_name;
        }
        return parent::getShippingMethodName();
    }
}
