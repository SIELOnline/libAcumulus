<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
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
    protected function setInvoiceSource($source)
    {
        parent::setInvoiceSource($source);
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
        $result = array_map(array($this, 'getItemLine'), $this->order->products);
        return $result;
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
        $result = array();
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
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::LineAmount => $item->order_product_total_price_no_vat,
                Meta::LineAmountInc => $item->order_product_total_price,
                Meta::VatAmount => $productVat,
            );
        }
        $result[Tag::Quantity] = $item->order_product_quantity;

        // Try to get the exact vat rate from the order-product info.
        // Note that this info remains correct when rates are changed as this
        // info is stored upon order creation in the order_product table.
        if (is_array($item->order_product_tax_info) && count($item->order_product_tax_info) === 1) {
            $productVatInfo = reset($item->order_product_tax_info);
            if (!empty($productVatInfo->tax_rate)) {
                $vatRate = $productVatInfo->tax_rate;
            }
        }

        if (isset($vatRate)) {
            $vatInfo = array(
                Tag::VatRate => 100.0 * $vatRate,
                Meta::VatRateSource => static::VatRateSource_Exact,
            );
        } else {
            $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);
        }
        $result += $vatInfo;

        // Add vat class meta data.
        if (isset($productVatInfo->category_namekey)) {
            $result[Meta::VatClassId] = $productVatInfo->category_namekey;
            /** @var \hikashopCategoryClass $categoryClass */
            $categoryClass = hikashop_get('class.category');
            $categoryClass->namekeys = array('category_namekey');
            /** @var stdClass $category */
            $category = $categoryClass->get($productVatInfo->category_namekey);
            if (isset($category->category_name)) {
                $result[Meta::VatClassName] = $category->category_name;
            }
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
        $result = array();

        foreach ($item->order_product_options as $key => $value) {
            // Skip numeric keys that have a StdClass as value.
            if (!is_numeric($key) && is_string($value)) {
                // Add variant.
                $result[] = array(
                    Tag::Product => $key . ': ' . $value,
                    Tag::UnitPrice => 0,
                    Tag::Quantity => $parentQuantity,
                ) + $vatRangeTags;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = array();
        // Check if there is a shipping id attached to the order.
        if (!empty($this->order->order_shipping_id)) {
            // Free shipping on a credit note will not be added as a line.
            if (!Number::isZero($this->order->order_shipping_price) || $this->invoiceSource->getType() !== Source::CreditNote) {
                $shippingInc = (float) $this->order->order_shipping_price;
                $shippingVat = (float) $this->order->order_shipping_tax;
                $shippingEx = $shippingInc - $shippingVat;
                $recalculatePrice = Tag::UnitPrice;
                $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, $this->precision, $this->precision);

                // Add vat lookup meta data.
                $vatLookupMetaData = array();
                if (!empty($this->order->order_shipping_params->prices)) {
                    $prices = $this->order->order_shipping_params->prices;
                    if (is_array($prices)) {
                        $price = reset($prices);
                        if (!empty($price->taxes) && is_array($price->taxes)) {
                            reset($price->taxes);
                            $vatKey = key($price->taxes);
                            if ($vatKey) {
                                /** @var \hikashopTaxClass $taxClass */
                                $taxClass = hikashop_get('class.tax');
                                $taxClass->namekeys = array('tax_namekey');
                                /** @var stdClass $tax */
                                $tax = $taxClass->get($vatKey);
                                if (!empty($tax->tax_namekey)) {
                                    $vatLookupMetaData += array(
                                        Meta::VatRateLookup => (float) $tax->tax_rate * 100,
                                        Meta::VatRateLookupLabel => $tax->tax_namekey,
                                    );
                                }
                            }
                        }
                    }
                }
                /** @var \hikashopShippingClass $shippingClass */
                $shippingClass = hikashop_get('class.shipping');
                /** @var stdClass $shipping */
                $shipping = $shippingClass->get($this->order->order_shipping_id);
                /** @var \hikashopCategoryClass $categoryClass */
                $categoryClass = hikashop_get('class.category');
                /** @var stdClass $category */
                $category = $categoryClass->get($shipping->shipping_tax_id);
                if (isset($category->category_namekey)) {
                    $vatLookupMetaData += array(
                        Meta::VatClassId => $category->category_namekey,
                        Meta::VatClassName => $category->category_name,
                    );
                }

                $result = array(
                        Tag::Product => $this->getShippingMethodName(),
                        Tag::Quantity => 1,
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::PrecisionUnitPriceInc => $this->precision,
                        Meta::RecalculatePrice => $recalculatePrice,
                        Meta::VatAmount => $shippingVat,
                    ) + $vatInfo + $vatLookupMetaData;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        /** @var \hikashopShippingClass $class */
        $class = hikashop_get('class.shipping');
        $shipping = $class->get($this->order->order_shipping_id);
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
        $result = array();

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

            $result[] = array(
                    Tag::Product => $description,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => -$discountEx,
                    Meta::UnitPriceInc => -$discountInc,
                    Meta::PrecisionUnitPriceInc => $this->precision,
                    Meta::RecalculatePrice => $recalculatePrice,
                    Meta::VatAmount => -$discountVat,
                ) + $vatInfo;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentFeeLine()
    {
        // @todo check (return on refund?)
        $result = array();
        if (!Number::isZero($this->order->order_payment_price)) {
            $paymentInc = (float) $this->order->order_payment_price;
            $paymentVat = (float) $this->order->order_payment_tax;
            $paymentEx = $paymentInc - $paymentVat;
            $recalculatePrice = Tag::UnitPrice;
            $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, $this->precision, $this->precision);
            $description = $this->t('payment_costs');

            // Add vat lookup meta data.
            $vatLookupMetaData = array();
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
                        $vatLookupMetaData += array(
                            Meta::VatClassId => $category->category_namekey,
                            Meta::VatClassName => $category->category_name,
                        );
                    }
                }
            }

            $result = array(
                    Tag::Product => $description,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => $paymentEx,
                    Meta::UnitPriceInc => $paymentInc,
                    Meta::PrecisionUnitPriceInc => $this->precision,
                    Meta::RecalculatePrice => $recalculatePrice,
                    Meta::VatAmount => $paymentVat,
                ) + $vatInfo + $vatLookupMetaData;
        }
        return $result;
    }
}
