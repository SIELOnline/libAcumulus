<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode  This is indeed a copy of the original Invoice\Creator.
 */

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Order_Item_Fee;

/**
 * Creates a raw version of the Acumulus invoice from a WooCommerce {@see Source}.
 *
 * @property \Siel\Acumulus\WooCommerce\Invoice\Source $invoiceSource
 */
class Creator extends BaseCreator
{
    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator and may be assumed exact. The computed one is based on the
     * subtraction/addition of 2 amounts, so has a precision that may be twice
     * as worse. WC tended to round amounts to the cent, but does not seem to
     * any longer do so.
     *
     * However, we still get reports of missed vat rates because they are out of
     * range, so we remain on the safe side and only use higher precision when
     * possible.
     */
    protected float $precision = 0.01;

    /**
     * @inheritdoc
     *
     * WooCommerce has general fee lines, so we have to override this method to
     * add these general fees (type unknown to us)
     */
    protected function getFeeLines(): array
    {
        $result = parent::getFeeLines();

        // So far, all amounts found on refunds are negative, so we probably
        // don't need to correct the sign on these lines either: but this has
        // not been tested yet!.
        foreach ($this->invoiceSource->getSource()->get_fees() as $feeLine) {
            $line = $this->getFeeLine($feeLine);
            $line = $this->addLineType($line, LineType::Other);
            $result[] = $line;
        }
        return $result;
    }

    /**
     * Returns an invoice line for 1 fee line.
     *
     * @param \WC_Order_Item_Fee $item
     *
     * @return array
     *   The invoice line for the given fee line.
     */
    protected function getFeeLine(WC_Order_Item_Fee $item): array
    {
        $quantity = $item->get_quantity();
        $feeEx = $item->get_total() / $quantity;
        $feeVat = $item->get_total_tax() / $quantity;

        return [
                Tag::Product => $this->t($item->get_name()),
                Tag::UnitPrice => $feeEx,
                Tag::Quantity => $item->get_quantity(),
                Meta::Id => $item->get_id(),
            ] + self::getVatRangeTags($feeVat, $feeEx, $this->precision, $this->precision);
    }
}
