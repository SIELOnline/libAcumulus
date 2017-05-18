<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;

/**
 * Defines WooCommerce specific invoice line flattener logic.
 */
class FlattenerInvoiceLines extends BaseFlattenerInvoiceLines
{
    /**
     * @inheritDoc
     *
     * This override adds support for the woocommerce-bundled-products plugin.
     * This plugin allows to define a base price for the parent but to keep
     * price info on the children, so this should all be added.
     */
    protected function collectInfoFromChildren(array $parent, array $children)
    {
        if (isset($parent['meta-bundle-id'])) {
            $childrenAmount = 0.0;
            foreach ($children as $child) {
                $childrenAmount += $child['unitprice'] * $child['quantity'];
            }
            $parent['unitprice'] +=  $childrenAmount / $parent['quantity'];
            $parent['meta-bundle-children-line-amount'] =  $childrenAmount;
        }
        return parent::collectInfoFromChildren($parent, $children);
    }
}
