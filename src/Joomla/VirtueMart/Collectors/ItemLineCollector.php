<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use DOMDocument;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use VirtueMartModelCustomfields;

/**
 * ItemLineCollector contains VirtueMart specific {@see LineType::Item} collecting logic.
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
     * @param \Siel\Acumulus\Data\Line $line
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Joomla\HikaShop\Invoice\Item $shopItem */
        $item = $propertySources->get('item');
        $shopItem = $item->getShopObject();

        $productPriceEx = (float) $shopItem->product_discountedPriceWithoutTax;
        $productPriceInc = (float) $shopItem->product_final_price;
        $productVat = (float) $shopItem->product_tax;
        $this->addVatData($line, 'VatTax', $productPriceEx, $productVat, (int) $shopItem->virtuemart_order_item_id);

        $line->unitPrice = $productPriceEx;
        $line->metadataSet(Meta::UnitPriceInc, $productPriceInc);
        $line->metadataSet(Meta::VatAmount, $productVat);
        $line->quantity = (int) $shopItem->product_quantity;

        // Add variant info.
        $this->addVariantLines($line, $shopItem);
    }

    /**
     * Adds child lines that describes this variant.
     *
     * @param object $item
     *   See {@see \hikashopOrder_productClass}
     */
    protected function addVariantLines(Line $line, object $item): void
    {
        // It is not possible (other than by copying a lot of awful code) to get
        // a list of separate attribute and value pairs. So we stick with
        // calling some code that prints the attributes on an order and
        // "disassemble" that code...
        if (!class_exists('VirtueMartModelCustomfields')) {
            /** @noinspection PhpIncludeInspection */
            require(VMPATH_ADMIN . '/models/customfields.php');
        }
        $product_attribute = VirtueMartModelCustomfields::CustomsFieldOrderDisplay($item);
        if (!empty($product_attribute)) {
            $document = new DOMDocument();
            $document->loadHTML($product_attribute);
            $spans = $document->getElementsByTagName('span');
            /** @var \DOMElement $span */
            foreach ($spans as $span) {
                // There tends to be a span around the list of spans containing
                // the actual text, ignore it and only process the lowest level
                // spans.
                if ($span->getElementsByTagName('span')->length === 0) {
                    /** @var Line $child */
                    $child = $this->createAcumulusObject();
                    $child->product = $span->textContent;
                    $child->unitPrice = 0;
                    $child->quantity = $line->quantity;
                    $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                    $line->addChild($child);
                }
            }
        }
    }
}
