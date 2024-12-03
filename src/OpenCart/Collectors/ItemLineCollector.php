<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;

use function count;

/**
 * ItemLineCollector contains OpenCart specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getItemLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param Line $line
     *   An item line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\OpenCart\Invoice\Item $item */
        $item = $propertySources->get('item');
        $product = $item->getProduct();
        $shopItem = $item->getShopObject();
        /** @var array|null $shopProduct */
        $shopProduct = $product?->getShopObject();

        // Get vat range info from item line.
        $productPriceEx = (float) $shopItem['price'];
        $productVat = (float) $shopItem['tax'];
        $line->unitPrice = $productPriceEx;
        $line->metadataSet(Meta::VatAmount, $productVat);
        $line->metadataSet(Meta::PrecisionUnitPrice, $this->precision);
        $line->metadataSet(Meta::PrecisionVatAmount, $this->precision);

        // Try to look up the vat rate via product.
        $this->addVatRateLookupMetadata($line, (int) $shopProduct['tax_class_id']);

        // Options (variants).
        $options = $item->getOrderProductOptions();
        if (count($options) !== 0) {
            // Add options as children.
            // In the old Creator we added all kinds of vat rate related metadata, but as
            // options do not have a price, this seems unnecessary. Just add the
            // VatRateSource::Parent as Meta::VatRateSource.
//            $vatMetadata[Meta::VatAmount] = 0;
//            $vatMetadata[Meta::VatRateSource] = VatRateSource::Parent;
//            $optionsVatInfo = $vatInfo; // $vatInfo is vat range tags + vat rate lookup metadata
//            $optionsVatInfo[Meta::VatAmount] = 0;
            foreach ($options as $option) {
                /** @var Line $child */
                $child = $this->createAcumulusObject();
                $child->product = "{$option['name']}: {$option['value']}";
                // Table order_option does not have a quantity field, so
                // composite products with multiple same sub product
                // are apparently not covered. Take quantity from parent.
                $child->quantity = $line->quantity;
                $child->unitPrice = 0;
                $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                $line->addChild($child);
            }
        }
    }
}
