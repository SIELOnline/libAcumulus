<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * DiscountLineCollector contains HikaShop specific {@see LineType::Discount} collecting
 * logic.
 *
 * Discounts in HikaShop seem to be a bit faulty.
 * - I could not get automatic taxes (property of a coupon) to work, not with coupons
 *   before, nor after taxes (setting under Config - Taxes).
 * - If you change a coupon from non-automatic to automatic, you leave a "dangling"
 *   tax-rate, which will be used instead of automatic tax rate selection.
 * - You could say that coupons after taxes are vouchers, because no vat is registered.
 *
 * Notes (comments copied over from the old Creator):
 * - HikaShop knows discounts in the form of coupons or unrestricted discounts.
 *   Coupons can be without vat (to be seen as partial payment, which was
 *   probably not meant, thus incorrect) or with a fixed vat rate, independent
 *   of the products in the cart, thus also incorrect.
 * - When a cart with a coupon contains products with another vat rate, the
 *   shown vat amount breakdown is incorrect. The Acumulus invoice will be
 *   correct, but may differ from the shop invoice, though the overall amount
 *   tends to be equal. It is the metadata in the invoice (as sent to Acumulus)
 *   that shows the differences.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class DiscountLineCollector extends LineCollector
{
    /**
     * A discount line with the mapped fields filled in.
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectDiscountLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the discount line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectDiscountLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $propertySources->get('source');
        $order = $source->getShopObject();

        $discountInc = (float) $order->order_discount_price;
        $discountVat = (float) $order->order_discount_tax;
        $discountEx = $discountInc - $discountVat;
        $recalculatePrice = Tag::UnitPrice;
        static::addVatRangeTags($line, $discountVat, $discountEx, $this->precision, $this->precision);
        if ($line->vatRate === null) {
            $line->metadataSet(Meta::StrategySplit, true);
        }
        $description = empty($order->order_discount_code)
            ? $this->t('discount')
            : $this->t('discount_code') . ' ' . $order->order_discount_code;

        $line->product = $description;
        $line->quantity = 1;
        $line->unitPrice = -$discountEx;
        $line->metadataSet(Meta::UnitPriceInc, -$discountInc);
        $line->metadataSet(Meta::PrecisionUnitPriceInc, $this->precision);
        $line->metadataSet(Meta::RecalculatePrice, $recalculatePrice);
        $line->metadataSet(Meta::VatAmount, -$discountVat);
    }
}
