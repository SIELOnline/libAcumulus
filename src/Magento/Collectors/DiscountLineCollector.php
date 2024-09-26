<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Magento\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * DiscountLineCollector contains Magento specific {@see LineType::Discount} collecting
 * logic.
 *
 * Discounts in Magento are stored at the order/creditmemo level. If product prices are
 * incl. VAT, the discount amount will also be incl. VAT.
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
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        $source = $propertySources->get('source');

        $line->itemNumber = '';
        $line->product = $this->getDiscountDescription($source);
        // Product prices incl. VAT => discount amount is also incl. VAT
        if ($this->productPricesIncludeTax()) {
            $line->metadataSet(Meta::UnitPriceInc, $source->getSign() * $source->getShopObject()->getBaseDiscountAmount());
        } else {
            $line->unitPrice = $source->getSign() * $source->getShopObject()->getBaseDiscountAmount();
        }
        $line->vatRate = null;
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
        $line->metadataSet(Meta::StrategySplit, true);
        $line->quantity = 1;
    }

    protected function getDiscountDescription(Source $source): string
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $source->getOrder()->getShopObject();

        if ($order->getDiscountDescription()) {
            $description = $this->t('discount_code') . ' ' . $order->getDiscountDescription();
        } elseif ($order->getCouponCode()) {
            $description = $this->t('discount_code') . ' ' . $order->getCouponCode();
        } else {
            $description = $this->t('discount');
        }
        return $description;
    }
}
