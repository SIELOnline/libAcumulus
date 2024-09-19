<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

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
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class DiscountLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectDiscountLine($acumulusObject);
    }

    /**
     * Collects the discount line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A discount line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectDiscountLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();

        $discountInc = (float) $order->order_discount_price;
        $discountVat = (float) $order->order_discount_tax;
        $discountEx = $discountInc - $discountVat;
        $recalculatePrice = Tag::UnitPrice;
        $this->addVatRangeTags($line, $discountVat, $discountEx, $this->precision, $this->precision);
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
