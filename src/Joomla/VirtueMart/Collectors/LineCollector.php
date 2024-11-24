<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

/**
 * LineCollector contains common VirtueMart specific line collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Precision of amounts stored in VM. In VM, you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     *
     * @var float
     */
    protected float $precision = 0.001;
    private array $order;

    protected function collectBefore(AcumulusObject $acumulusObject, PropertySources $propertySources, array &$fieldSpecifications): void
    {
        parent::collectBefore($acumulusObject, $propertySources, $fieldSpecifications);
        $this->order = $propertySources->get('source')->getShopObject();
    }

    /**
     * Adds vat data and vat lookup metadata to the current (item) line.
     *
     * @param int $orderItemId
     *   Type of calc rule to search for: 'VatTax', 'shipment' or 'payment'.
     * @param string $calcRuleType
     *   The order item to search the calc rule for, or search at the order
     *   level if left empty.
     */
    protected function addVatData(Line $line, string $calcRuleType, float $vatAmount, int $orderItemId = 0): void
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
            $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
            $line->metadataSet(Meta::PrecisionVatAmount, $this->precision);
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
        $order = $this->order;
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
