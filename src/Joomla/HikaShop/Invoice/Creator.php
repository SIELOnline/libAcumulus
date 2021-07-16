<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use stdClass;

/**
 * Allows to create arrays in the Acumulus invoice structure from a HikaShop
 * order
 *
 * Notes:
 * - HikaShop knows discounts in the form of coupons or unrestricted discounts.
 *   Coupons can be without vat (to be seen as partial payment, which was
 *   probably not meant, thus incorrect) or with a fixed vat rate, independent
 *   from the products in the cart, thus also incorrect.
 * - When a cart with a coupon contains products with another vat rate, the
 *   shown vat amount breakdown is incorrect. The Acumulus invoice will be
 *   correct, but may differ from the shop invoice, though the overall amount
 *   tends to be equal. It is the meta data in the invoice (as sent to Acumulus)
 *   that shows the differences.
 */
class Creator extends BaseCreator
{
    /**
     * @var object
     */
    protected $order;

    /**
     * Precision of amounts stored in HS. In HS you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 5
     * digits precision. So 0.0001 is on the pessimistic side.
     *
     * @var float
     */
    protected $precision = 0.0002;

    /**
     * {@inheritdoc}
     *
     * This override also initializes HS specific properties related to the
     * source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);
        $this->order = $this->invoiceSource->getSource();
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        if (!empty($this->order->billing_address)) {
            $this->propertySources['billing_address'] = $this->order->billing_address;
        }
        if (!empty($this->order->shipping_address)) {
            $this->propertySources['shipping_address'] = $this->order->shipping_address;
        }
        $this->propertySources['customer'] = $this->order->customer;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        return array_map([$this, 'getItemLine'], $this->order->products);
    }

    /**
     * Returns 1 item line for 1 product line.
     *
     * @param stdClass $item
     *
     * @return array
     */
    protected function getItemLine(stdClass $item)
    {
        $result = [];
        $this->addPropertySource('item', $item);
        $this->addProductInfo($result);
        // Remove html with variant info from product name, we'll add that later
        // using children lines.
        if (isset($result[Tag::Product]) && ($pos = strpos($result[Tag::Product], '<span')) !== false) {
            $result[Tag::Product] = substr($result[Tag::Product], 0, $pos);
        }

        $productPriceEx = (float) $item->order_product_price;
        $productVat = (float) $item->order_product_tax;

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $productPriceEx + $productVat;
        } else {
            $result += [
                Tag::UnitPrice => $productPriceEx,
                Meta::LineAmount => $item->order_product_total_price_no_vat,
                Meta::LineAmountInc => $item->order_product_total_price,
                Meta::VatAmount => $productVat,
            ];
        }
        $result[Tag::Quantity] = $item->order_product_quantity;

        // Try to get the exact vat rate from the order-product info.
        // Note that this info remains correct when rates are changed as this
        // info is stored upon order creation in the order_product table.
        if (is_array($item->order_product_tax_info)) {
            if (count($item->order_product_tax_info) === 1) {
                $productVatInfo = reset($item->order_product_tax_info);
                if (isset($productVatInfo->tax_rate)) {
                    $vatRate = $productVatInfo->tax_rate;
                }
            } elseif (count($item->order_product_tax_info) === 0) {
                $result[Meta::VatClassId] = Config::VatClass_Null;
            } else {
                $result[Meta::Warning] = 'Cumulative vat rates applied: unknown in NL';
            }
        }

        if (isset($vatRate)) {
            $vatInfo = [
                Tag::VatRate => 100.0 * $vatRate,
                Meta::VatRateSource => Number::isZero($productVat) ? Creator::VatRateSource_Exact0 : Creator::VatRateSource_Exact,
            ];
        } else {
            $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);
        }
        $result += $vatInfo;

        // Add vat class meta data.
        if (isset($productVatInfo->category_namekey)) {
            $result[Meta::VatClassId] = $productVatInfo->category_namekey;
            /** @var \hikashopCategoryClass $categoryClass */
            $categoryClass = hikashop_get('class.category');
            $categoryClass->namekeys = ['category_namekey'];
            /** @var stdClass $category */
            $category = $categoryClass->get($productVatInfo->category_namekey);
            if (isset($category->category_name)) {
                $result[Meta::VatClassName] = $category->category_name;
            }

            // Add vat rate meta data.
            // We can use hikashopCurrencyClass::getTax() to get a tax rate.
            // This method wants:
            // - The zone - state or country - where the customer lives.
            // - The customer type: we should use 'individual' to prevent
            //   getting 0% (vat exempt) when the customer is a
            //  'company_with_vat_number'.
            // - The category id of the tax class, which we have in $category.
            if (isset($category->category_id)) {
                $address = $this->order->billing_address;
                $zone_name = !empty($address->address_state_orig) ? $address->address_state_orig : $address->address_country_orig;
                if (empty($zone_name)) {
                    $address = $this->order->shipping_adress;
                    $zone_name = !empty($address->address_state_orig) ? $address->address_state_orig : $address->address_country_orig;
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
                        $result[Meta::VatRateLookup] =  (float) $vatRate * 100;
                    }
                }
            }
        } elseif (is_array($item->order_product_tax_info) && count($item->order_product_tax_info) === 0) {
            // We do not have any order_product_vat_info at all: the product
            // does not have any tax category assigned.
            $result[Meta::VatClassId] = Config::VatClass_Null;
        }

        // Add variant info.
        if (!empty($item->order_product_options)) {
            $children = $this->getVariantLines($item, $result[Tag::Quantity], $vatInfo);
            if (!empty($children)) {
                $result[Meta::ChildrenLines] = $children;
            }
        }

        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * @param stdClass $item
     * @param int $parentQuantity
     * @param array $vatRangeTags
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines(stdClass $item, $parentQuantity, $vatRangeTags)
    {
        $result = [];

        foreach ($item->order_product_options as $key => $value) {
            // Skip numeric keys that have a StdClass as value.
            if (!is_numeric($key) && is_string($value)) {
                // Add variant.
                $result[] = [
                    Tag::Product => $key . ': ' . $value,
                    Tag::UnitPrice => 0,
                    Tag::Quantity => $parentQuantity,
                ] + $vatRangeTags;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * Note: HS 4+ has a setting on shipping methods "Automatic taxes"/
     * "Automatische belastingen" that determines how the vat on shipping is
     * calculated. It is stored in shipping_params->shipping_tax and can have
     * the following values:
     * 0 - No/Nee: use the specified "Product tax category"/"Product btw
     *   categorie" to get the vat rate.
     * 1 - Proportion/Verhouding: use the weighed average vat rate on the item
     *   lines as vat rate for the shipping. This results in multiple shipping
     *   lines per used shipping method if different vat rates were applied on
     *   the item lines.
     * 2 - Highest rate/Hoogste waarde: use the highest vat rate from the item
     *   lines as vat rate for the shipping.
     *
     * Real-life examples of order->order_shipping_params:
     * - {"prices":{"27@0":{"price_with_tax":"20.00000","tax":0}}}
     * - {"prices":{"30@0":{"price_with_tax":8,"tax":0,"taxes":{"EUICL":0}}}}
     * - {"prices":{"6@0":{"price_with_tax":4.000018,"tax":0.694218,"taxes":{"BTW":0.694218}}}}
     * - {"prices":{"5@0":{"price_with_tax":4.6177850629697,"tax":0.48558506296965,"taxes":{"BTW Laag":0.11063016598247,"BTW 0%":0,"BTW":0.37495489698718}}}}
     *
     * Explanation:
     * - stdClass|null order->order_shipping_params: may only be null if no or
     *   free shipping. Has 1 property:
     * - stdClass[] prices: with 1 entry per shipment - so normally 1 entry -,
     *   keyed like {shipping_method_id}@{index}. Each entry is a stdClass with
     *   properties:
     * - float price_with_tax: price including tax for this shipment
     * - float tax: (total) tax for this shipment
     * - (optional) float[]: array of float (tax amount) keyed by tax class
     *   name. If a shipping method has proportional tax rates (i.e. following
     *   the contents of the cart) this array contains the proportion of the
     *   (total) tax per tax class.
     */
    protected function getShippingLines()
    {
        $result = [];
        if (Number::isZero($this->order->order_shipping_price)) {
            // Free (or no) shipping: do not add on a credit note.
            if ($this->invoiceSource->getType() !== Source::CreditNote) {
                // @nth: can we distinguish between free shipping and in-store pickup?
                $result[] = [
                    Tag::Product => $this->t('free_shipping'),
                    Tag::Quantity => 1,
                    Tag::UnitPrice => 0.0,
                    Tag::VatRate => null,
                    Meta::VatRateSource => static::VatRateSource_Completor,
                ];
            }
        } elseif (empty($this->order->order_shipping_params)) {
            // If the property order_shipping_params is not set, we use the
            // order_shipping_* properties at the order level.
            // @nth: can we distinguish between free shipping and in-store pickup?
            $result[] = [
                Tag::Product => $this->getShippingMethodName($this->order->order_shipping_id),
                Tag::Quantity => 1,
                Meta::UnitPriceInc => $this->order->order_shipping_price,
                Meta::VatAmount => $this->order->order_shipping_tax,
                Tag::VatRate => null,
                Meta::VatRateSource => static::VatRateSource_Completor,
            ];
        } else {
            // For each shipment we are going to add 1 or more shipping lines.
            /** @var \hikashopTaxClass $taxClass */
            $taxClass = hikashop_get('class.tax');

            $shippingAmountIncTotal = 0.0;
            $shippingVatTotal = 0.0;
            $warningAdded = false;

            foreach ($this->order->order_shipping_params->prices as $key => $price) {
                list($shipping_id, $index) = explode('@', $key);
                $shippingLine = [
                    Tag::Product => $this->getShippingMethodName($shipping_id),
                    Tag::Quantity => 1,
                ];
                if ($index > 0) {
                    $shippingLine[Tag::Product] .= sprintf(' %d', $index + 1);
                }

                if (!isset($price->taxes)) {
                    // No tax breakdown (probably because there's no tax).
                    $result[] = $shippingLine + [
                            Meta::UnitPriceInc => $price->price_with_tax,
                            Meta::VatAmount => $price->tax,
                            Meta::VatRateSource => static::VatRateSource_Completor,
                        ];
                } elseif (count($price->taxes) === 1) {
                    // Single tax applied, add the same fields as above but with
                    // some additional meta data.
                    reset($price->taxes);
                    $taxNameKey = key($price->taxes);
                    $result[] = $shippingLine + [
                            Meta::UnitPriceInc => $price->price_with_tax,
                            Meta::VatAmount => $price->tax,
                            Meta::VatRateSource => static::VatRateSource_Completor,
                            Meta::VatClassName => $taxNameKey,
                            Meta::VatRateLookup => (float) $taxClass->get($taxNameKey)->tax_rate,
                        ];
                } else {
                    // Multiple taxes applied tot his shipment: add a line per
                    // vat rate.
                    $shippingMethodAmountIncTotal = 0.0;
                    $addMissingAmountIndex = null;
                    foreach ($price->taxes as $taxNameKey => $shippingVat) {
                        $vatRate = (float) $taxClass->get($taxNameKey)->tax_rate;
                        if (!Number::isZero($vatRate)) {
                            $shippingEx = $shippingVat / $vatRate;
                        } else {
                            // If the rate = 0, we can not compute the price ex,
                            // so we fill in 0.0 for now and will fill it with
                            // the missing amount at the end of this loop.
                            $shippingEx = 0.0;
                            $addMissingAmountIndex = count($result);
                        }
                        $result[] = $shippingLine + [
                                Tag::UnitPrice => $shippingEx,
                                Meta::VatAmount => $shippingVat,
                                Tag::VatRate => 100.0 * $vatRate,
                                Meta::VatRateSource => static::VatRateSource_Creator_Lookup,
                                Meta::VatClassName => $taxNameKey,
                                Meta::VatRateLookup => 100.0 * $vatRate,
                            ];
                        $shippingMethodAmountIncTotal += $shippingEx + $shippingVat;
                    }
                    // Fill in the missing amount if we had a 0 rate.
                    if ($addMissingAmountIndex !== null) {
                        $result[$addMissingAmountIndex][Tag::UnitPrice] = $price->price_with_tax - $shippingMethodAmountIncTotal;
                        $shippingMethodAmountIncTotal += $result[$addMissingAmountIndex][Tag::UnitPrice];
                    }
                    if (!Number::floatsAreEqual($shippingMethodAmountIncTotal, $price->price_with_tax)) {
                        // Problem: rates have probably changed.
                        $result[count($result) - 1][Meta::Warning] =
                            'Amounts for this shipping method do not add up: rates have probably changed.'
                            . ' (order_shipping_params->prices = '
                            . json_encode($this->order->order_shipping_params->prices)
                            . ')';
                        $warningAdded = true;
                    }
                }
                $shippingAmountIncTotal += $price->price_with_tax;
                $shippingVatTotal += $price->tax;
            }
            if (!Number::floatsAreEqual($shippingAmountIncTotal, $this->order->order_shipping_price)
                || !Number::floatsAreEqual($shippingVatTotal, $this->order->order_shipping_tax)) {
                // Problem: lost too much precision? (or we had a rate that has
                // changed: we will already have discovered that above, so we do
                // not produce this warning here.)
                if (!$warningAdded) {
                    $result[count($result) - 1][Meta::Warning] = 'Amounts for the shipping method(s) do not add up: lost too much precision?'
                        . ' (order_shipping_params->prices = '
                        . json_encode($this->order->order_shipping_params->prices)
                        . ')';
                }
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        throw new RuntimeException(__METHOD__ . ' should never be called');
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        $shipping_id = func_num_args() > 0 ? func_get_arg(0) : $this->order->order_shipping_id;

        /** @var \hikashopShippingClass $class */
        $class = hikashop_get('class.shipping');
        $shipping = $class->get($shipping_id);
        if (!empty($shipping->shipping_name)) {
            return $shipping->shipping_name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = [];

        if (!Number::isZero($this->order->order_discount_price)) {
            $discountInc = (float) $this->order->order_discount_price;
            $discountVat = (float) $this->order->order_discount_tax;
            $discountEx = $discountInc - $discountVat;
            $recalculatePrice = Tag::UnitPrice;
            $vatInfo = $this->getVatRangeTags($discountVat, $discountEx, $this->precision, $this->precision);
            if ($vatInfo[Tag::VatRate] === null) {
                $vatInfo[Meta::StrategySplit] = true;
            }
            $description = empty($this->order->order_discount_code)
                ? $this->t('discount')
                : $this->t('discount_code') . ' ' . $this->order->order_discount_code;

            $result[] = [
                    Tag::Product => $description,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => -$discountEx,
                    Meta::UnitPriceInc => -$discountInc,
                    Meta::PrecisionUnitPriceInc => $this->precision,
                    Meta::RecalculatePrice => $recalculatePrice,
                    Meta::VatAmount => -$discountVat,
                        ] + $vatInfo;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentFeeLine()
    {
        // @todo check (return on refund?)
        $result = [];
        if (!Number::isZero($this->order->order_payment_price)) {
            $paymentInc = (float) $this->order->order_payment_price;
            $paymentVat = (float) $this->order->order_payment_tax;
            $paymentEx = $paymentInc - $paymentVat;
            $recalculatePrice = Tag::UnitPrice;
            $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, $this->precision, $this->precision);
            $description = $this->t('payment_costs');

            // Add vat lookup meta data.
            $vatLookupMetaData = [];
            if (!empty($this->order->order_payment_id)) {
                /** @var \hikashopShippingClass $paymentClass */
                $paymentClass = hikashop_get('class.payment');
                /** @var stdClass $payment */
                $payment = $paymentClass->get($this->order->order_payment_id);
                if (!empty($payment->payment_params->payment_tax_id)) {
                    /** @var \hikashopCategoryClass $categoryClass */
                    $categoryClass = hikashop_get('class.category');
                    /** @var stdClass $category */
                    $category = $categoryClass->get($payment->payment_params->payment_tax_id);
                    if (isset($category->category_namekey)) {
                        $vatLookupMetaData += [
                            Meta::VatClassId => $category->category_namekey,
                            Meta::VatClassName => $category->category_name,
                        ];
                    }
                }
            }

            $result = [
                    Tag::Product => $description,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => $paymentEx,
                    Meta::UnitPriceInc => $paymentInc,
                    Meta::PrecisionUnitPriceInc => $this->precision,
                    Meta::RecalculatePrice => $recalculatePrice,
                    Meta::VatAmount => $paymentVat,
                ] + $vatInfo + $vatLookupMetaData;
        }
        return $result;
    }
}
