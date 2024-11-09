<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * PaymentFeeLineCollector contains HikaShop specific {@see LineType::PaymentFee}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class PaymentFeeLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectPaymentFeeLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the payment fee line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectPaymentFeeLine(Line $line, PropertySources $propertySources): void
    {
        /** @var Source $source */
        $source = $propertySources->get('source');

        $paymentInc = (float) $source->getShopObject()->order_payment_price;
        $paymentVat = (float) $source->getShopObject()->order_payment_tax;
        $paymentEx = $paymentInc - $paymentVat;
        $recalculatePrice = Fld::UnitPrice;
        $description = $this->t('payment_costs');
        $line->product = $description;
        $line->quantity = 1;
        $line->unitPrice = $paymentEx;
        $line->metadataSet(Meta::VatAmount, $paymentVat);
        $line->metadataSet(Meta::PrecisionUnitPrice, $this->precision);
        $line->metadataSet(Meta::PrecisionVatAmount, $this->precision);
        $line->metadataSet(Meta::UnitPriceInc, $paymentInc);
        $line->metadataSet(Meta::PrecisionUnitPriceInc, $this->precision);
        $line->metadataSet(Meta::RecalculatePrice, $recalculatePrice);

        // Add vat lookup meta data.
        if (!empty($source->getShopObject()->order_payment_id)) {
            /** @var \hikashopShippingClass $paymentClass */
            $paymentClass = hikashop_get('class.payment');
            /** @var \stdClass $payment */
            $payment = $paymentClass->get($source->getShopObject()->order_payment_id);
            if (!empty($payment->payment_params->payment_tax_id)) {
                /** @var \hikashopCategoryClass $categoryClass */
                $categoryClass = hikashop_get('class.category');
                /** @var \stdClass $category */
                $category = $categoryClass->get($payment->payment_params->payment_tax_id);
                if (isset($category->category_namekey)) {
                    $line->metadataSet(Meta::VatClassId, $category->category_namekey);
                    $line->metadataSet(Meta::VatClassName, $category->category_name);
                }
            }
        }
    }
}

