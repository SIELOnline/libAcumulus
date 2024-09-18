<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use stdClass;

/**
 * Creates a raw version of the Acumulus invoice from a HikaShop {@see Source}.
 *
 * Notes:
 * - HikaShop knows discounts in the form of coupons or unrestricted discounts.
 *   Coupons can be without vat (to be seen as partial payment, which was
 *   probably not meant, thus incorrect) or with a fixed vat rate, independent
 *   of the products in the cart, thus also incorrect.
 * - When a cart with a coupon contains products with another vat rate, the
 *   shown vat amount breakdown is incorrect. The Acumulus invoice will be
 *   correct, but may differ from the shop invoice, though the overall amount
 *   tends to be equal. It is the metadata in the invoice (as sent to Acumulus)
 *   that shows the differences.
 */
class Creator extends BaseCreator
{
    protected object $order;
    /**
     * Precision of amounts stored in HS. In HS you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 5
     * digits precision. So 0.0001 is on the pessimistic side.
     */
    protected float $precision = 0.0002;

    /**
     * {@inheritdoc}
     *
     * This override also initializes HS specific properties related to the
     * source.
     */
    protected function setInvoiceSource(Source $invoiceSource): void
    {
        parent::setInvoiceSource($invoiceSource);
        $this->order = $this->invoiceSource->getSource();
    }

    protected function setPropertySources(): void
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

    protected function getDiscountLines(): array
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

    protected function getPaymentFeeLine(): array
    {
        // @todo: check (return on refund?)
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
