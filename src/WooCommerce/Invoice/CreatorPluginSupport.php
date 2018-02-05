<?php

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Booking;
use WC_Booking_Data_Store;
use WC_Order_Item_Product;
use WC_Product;

/**
 * CreatorSupportForOtherPlugins contains support for other plugins.
 *
 * The WooCommerce field contains many additional plugins that add features
 * to standard WooCommerce. Supporting all these plugins is difficult and can
 * lead to hard to read and maintain code. Therefore we try to split support for
 * these other plugins off into its own containers reacting to the Acumulus
 * filters and actions
 */
class CreatorPluginSupport
{
    /**
     * Called at the beginning of Creator::getItemLine().
     *
     * @param \Siel\Acumulus\WooCommerce\Invoice\Creator $creator
     * @param WC_Order_Item_Product $item
     *   An array representing an order item line, meta values are already
     *   available under their own names and as an array under key 'item_meta'.
     * @param WC_Product|bool $product
     *   The product that was sold on this line, may also be a bool according to
     *   the WC3 php documentation. I guess it will be false if the product has
     *   been deleted since.
     */
    public function getItemLineBefore(Creator $creator, WC_Order_Item_Product $item, $product)
    {
        $this->getItemLineBeforeBookings($creator, $item, $product);
    }

    /**
     * Called at the end of Creator::getItemLine().
     *
     * @param \Siel\Acumulus\WooCommerce\Invoice\Creator $creator
     * @param WC_Order_Item_Product $item
     *   An array representing an order item line, meta values are already
     *   available under their own names and as an array under key 'item_meta'.
     * @param WC_Product|bool $product
     *   The product that was sold on this line, may also be a bool according to
     *   the WC3 php documentation. I guess it will be false if the product has
     *   been deleted since.
     */
    public function getItemLineAfter(
        Creator $creator,
        /** @noinspection PhpUnusedParameterInspection */ WC_Order_Item_Product $item,
        /** @noinspection PhpUnusedParameterInspection */$product
    ) {
        $this->getItemLineAfterBookings($creator);
    }

    /**
     * Support for the "WooCommerce Bookings" plugin.
     *
     * Bookings are stored in a separate entity, we add that as a separate
     * property source, so its properties can be used.
     *
     * @param \Siel\Acumulus\WooCommerce\Invoice\Creator $creator
     * @param WC_Order_Item_Product $item
     * @param WC_Product|bool $product
     */
    public function getItemLineBeforeBookings(Creator $creator, WC_Order_Item_Product $item, $product)
    {
        if ($product instanceof WC_Product) {
            if (function_exists('is_wc_booking_product') && is_wc_booking_product($product)) {
                $booking_ids = WC_Booking_Data_Store::get_booking_ids_from_order_item_id($item->get_id());
                if ($booking_ids) {
                    // I cannot imagine multiple bookings belonging to the same
                    // order line, but if that occurs, only the 1st booking will
                    // be added as a property source.
                    $booking = new WC_Booking(reset($booking_ids));
                    $creator->addPropertySource('booking', $booking);
                }
            }
        }
    }

    /**
     * Support for the "WooCommerce Bookings" plugin.
     *
     * Removes the property source.
     *
     * @param \Siel\Acumulus\WooCommerce\Invoice\Creator $creator
     */
    public function getItemLineAfterBookings(Creator $creator)
    {
        $creator->removePropertySource('booking');
    }

    /**
     * Filter that reacts to the acumulus_invoice_created event.
     *
     * @param array|null $invoice
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param \Siel\Acumulus\Invoice\Result $localResult
     *
     * @return array|null
     *
     */
    public function acumulusInvoiceCreated($invoice, BaseSource $invoiceSource, Result $localResult)
    {
        //$invoice = $this->supportPlugin1($invoice, $invoiceSource, $localResult);
        return $invoice;
    }

    /**
     *
     *
     * @param array|null $invoice
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param \Siel\Acumulus\Invoice\Result $localResult
     *
     * @return array|null
     *
     */
    protected function supportPlugin1($invoice, BaseSource $invoiceSource, Result $localResult)
    {
        // Here you can make changes to the raw invoice based on your specific
        // situation, e.g. setting or correcting tax rates before the completor
        // strategies execute.
        return $invoice;
    }

}
