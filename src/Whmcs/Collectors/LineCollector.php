<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Collectors;

use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Item;

/**
 * LineCollector adds WHMCS line collecting logic.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    protected function collectAfter(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $item = $propertySources['item'];
        if ($item instanceof Item) {
            if (str_starts_with($item->getShopObject()['type'], 'Promo')) {
                $acumulusObject->setType(LineType::Discount);
            }
        }
        parent::collectAfter($acumulusObject, $propertySources);
    }

}
