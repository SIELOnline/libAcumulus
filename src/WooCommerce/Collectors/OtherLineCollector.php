<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;

/**
 * OtherLineCollector contains WooCommerce specific {@see LineType::Other}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class OtherLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A fee line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectFeeLine($acumulusObject);
    }

    /**
     * So far, all amounts found on refunds are negative, so we probably don't need to
     * correct the sign on these lines either: but this has not been tested yet!.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A fee line with the mapped fields filled in.
     */
    protected function collectFeeLine(Line $line): void
    {
        /**
         * @var \WC_Order_Item_Fee $fee
         */
        $fee = $this->getPropertySource('otherLineInfo');

        $quantity = $fee->get_quantity();
        $feeEx = $fee->get_total() / $quantity;
        $feeVat = $fee->get_total_tax() / $quantity;

        $line->product = $this->t($fee->get_name());
        $line->unitPrice = $feeEx;
        $line->quantity = $fee->get_quantity();
        $line->metadataSet(Meta::Id, $fee->get_id());
        self::addVatRangeTags($line, $feeVat, $feeEx, $this->precision, $this->precision);
    }
}
