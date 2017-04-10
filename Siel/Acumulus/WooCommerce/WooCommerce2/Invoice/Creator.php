<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\WooCommerce\Invoice\Creator as BaseCreator;

/**
 * Allows to create an Acumulus invoice from a WooCommerce2 order or refund.
 *
 * This class only overrides methods that contain non BC compatible changes of
 * WooCommerce 3.
 */
class Creator extends BaseCreator
{
    /**
     * Precision in WC2: one of the prices is entered by the administrator and
     * thus can be considered exact. The computed one is not rounded, so we can
     * assume a very high precision for all values here.
     *
     * @var float
     */
    protected $precision  = 0.001;

    /**
     * Token callback to access the order post meta when resolving tokens.
     *
     * @param string $property
     *
     * @return null|string
     *   The value for the meta data with the given name, null if not available.
     */
    public function getOrderMeta($property) {
        $value = get_post_meta($this->order->id, $property, true);
        // get_post_meta() can return false or ''.
        if (empty($value)) {
            // Not found: indicate so by returning null.
            $value = null;
        }
        return $value;
    }

}
