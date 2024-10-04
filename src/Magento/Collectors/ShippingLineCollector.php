<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * ShippingLineCollector contains Magento specific {@see LineType::Shipping} collecting
 * logic.
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
        $this->getShippingLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the shipping line for an invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A shipping line with the mapped fields filled in.
     */
    protected function getShippingLine(Line $line, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        $source = $propertySources->get('source');
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $shopSource */
        $shopSource = $source->getShopObject();

        $line->product = $this->getShippingMethodName($source);
        $line->quantity = 1;
        // What do the following methods return?
        // - getBaseShippingAmount(): shipping costs ex VAT ex any discount.
        // - getBaseShippingInclTax(): shipping costs inc VAT ex any discount.
        // - getBaseShippingTaxAmount(): VAT on shipping costs inc discount.
        // - getBaseShippingDiscountAmount(): discount on shipping inc VAT.
        if (!Number::isZero($shopSource->getBaseShippingAmount())) {
            // We have 2 ways of calculating the vat rate: first one is
            // based on tax amount and normal shipping costs corrected with
            // any discount (as the tax amount is including any discount):
            // $vatRate1 = $magentoSource->getBaseShippingTaxAmount() / ($magentoSource->getBaseShippingInclTax()
            //   - $magentoSource->getBaseShippingDiscountAmount() - $magentoSource->getBaseShippingTaxAmount());
            // However, we will use the 2nd way as that seems to be more
            // precise and thus generally leads to a smaller range:
            // Get range based on normal shipping costs inc and ex VAT.
            $sign = $source->getSign();
            $shippingInc = $sign * $shopSource->getBaseShippingInclTax();
            $shippingEx = $sign * $shopSource->getBaseShippingAmount();
            $shippingVat = $shippingInc - $shippingEx;
            $line->unitPrice = $shippingEx;
            $line->metadataSet(Meta::VatAmount, $shippingVat);
            $line->metadataSet(Meta::PrecisionUnitPrice, $this->shippingPriceIncludeTax() ? 0.02 : 0.01);
            $line->metadataSet(Meta::PrecisionVatAmount, 0.02);
            $line->metadataSet(Meta::UnitPriceInc, $shippingInc);
            $line->metadataSet(Meta::RecalculatePrice, $this->shippingPriceIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc);
            $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);

            // Add vat class meta data.
            $this->addVatClassMetaData($line, $this->getShippingTaxClassId());

            // getBaseShippingDiscountAmount() only exists on Orders.
            if ($source->getType() === Source::Order && !Number::isZero($shopSource->getBaseShippingDiscountAmount())) {
                $tag = $this->discountIncludesTax() ? Meta::LineDiscountAmountInc : Meta::LineDiscountAmount;
                $line->metadataSet($tag, -$sign * $shopSource->getBaseShippingDiscountAmount());
            } elseif ($source->getType() === Source::CreditNote
                && !Number::floatsAreEqual($shippingVat, $shopSource->getBaseShippingTaxAmount(), 0.02)
            ) {
                // On credit notes, the shipping discount amount is not
                // stored but can be deduced via the shipping discount tax
                // amount and the shipping vat rate. To get a more precise
                // Meta::LineDiscountAmountInc, we compute that in the
                // completor when we have corrected the vat rate.
                $line->metadataSet(Meta::LineDiscountVatAmount, $sign * ($shippingVat - $sign * $shopSource->getBaseShippingTaxAmount()));
            }
        } else {
            // Free shipping should get a "normal" tax rate. We leave that
            // to the completor to determine.
            $line->unitPrice = 0;
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        }
    }

    protected function getShippingMethodName(mixed ...$args): string
    {
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        [$source] = $args;
        $name = $source->getOrder()->getShopObject()->getShippingDescription();
        return !empty($name) ? $name : parent::getShippingMethodName();
    }
}
