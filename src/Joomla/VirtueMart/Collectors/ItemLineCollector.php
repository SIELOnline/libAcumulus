<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use DOMDocument;
use Siel\Acumulus\Api;
use Siel\Acumulus\Collectors\LineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
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
     * Precision of amounts stored in VM. In VM, you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     *
     * @var float
     */
    protected float $precision = 0.001;

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->getItemLine($acumulusObject);
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
    protected function getItemLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getPropertySource('invoice');
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();
        /** @var \Siel\Acumulus\Joomla\HikaShop\Invoice\Item $shopItem */
        $item = $this->getPropertySource('item');
        $shopItem = $item->getShopObject();

        $productPriceEx = (float) $shopItem->product_discountedPriceWithoutTax;
        $productPriceInc = (float) $shopItem->product_final_price;
        $productVat = (float) $shopItem->product_tax;
        $this->addVatData($line, 'VatTax', $productPriceEx, $productVat, (int) $shopItem->virtuemart_order_item_id);

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $line->unitPrice = $productPriceInc;
        } else {
            $line->unitPrice = $productPriceEx;
            $line->metadataSet(Meta::UnitPriceInc, $productPriceInc);
            $line->metadataSet(Meta::VatAmount, $productVat);
        }
        $line->quantity = (int) $shopItem->product_quantity;

        // Add variant info.
        $this->addVariantLines($line, $shopItem);
    }

    /**
     * Adds vat data and vat lookup metadata to the current (item) line.
     *
     * @param string $calcRuleType
     *   Type of calc rule to search for: 'VatTax', 'shipment' or 'payment'.
     * @param int $orderItemId
     *   The order item to search the calc rule for, or search at the order
     *   level if left empty.
     */
    protected function addVatData(Line $line, string $calcRuleType, float $amountEx, float $vatAmount, int $orderItemId = 0): void
    {
        $calcRule = $this->getCalcRule($calcRuleType, $orderItemId);
        if ($calcRule !== null && !empty($calcRule->calc_value)) {
            $line->vatRate = (float) $calcRule->calc_value;
            $line->metadataSet(Meta::VatRateSource, Number::isZero($vatAmount) ? VatRateSource::Exact0 : VatRateSource::Exact);
            $line->metadataSet(Meta::VatClassId, $calcRule->virtuemart_calc_id);
            $line->metadataSet(Meta::VatClassName, $calcRule->calc_rule_name);
        } elseif (Number::isZero($vatAmount)) {
            // No vat class assigned to payment or shipping fee.
            $line->vatRate = Api::VatFree;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact0);
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        } else {
            static::addVatRangeTags($line, $vatAmount, $amountEx, $this->precision);
        }
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

    /**
     * Returns a calculation rule identified by the given reference
     *
     * @param string $calcKind
     *   The value for the kind of calc rule.
     * @param int $orderItemId
     *   The value for the order item id, or 0 for special lines.
     *
     * @return null|object
     *   The (1st) calculation rule for the given reference, or null if none
     *   found.
     */
    protected function getCalcRule(string $calcKind, int $orderItemId = 0): ?object
    {
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();
        foreach ($order['calc_rules'] as $calcRule) {
            if ($calcRule->calc_kind === $calcKind) {
                if (empty($orderItemId) || (int) $calcRule->virtuemart_order_item_id === $orderItemId) {
                    return $calcRule;
                }
            }
        }
        return null;
    }
}
