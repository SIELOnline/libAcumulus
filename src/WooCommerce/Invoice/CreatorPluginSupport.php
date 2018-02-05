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
     * Supports the "WooCommerce Bookings" plugin.
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
        $invoice = $this->supportBundleProducts($invoice, $invoiceSource, $localResult);
        return $invoice;
    }

    /**
     * Supports the "WooCommerce Bundle Products" plugin.
     *
     * This method supports the woocommerce-product-bundles extension that
     * stores the bundle products as separate item lines below the bundle line
     * and uses the meta data described below to link them to each other.
     *
     * This method hierarchically groups bundled products into the bundle
     * product and can do so multi-level.
     *
     * Meta data on bundle lines:
     * - bundle_cart_key (hash) unique identifier.
     * - bundled_items (hash[]) refers to the bundle_cart_key of the bundled
     *     products.
     *
     * Meta data on bundled items:
     * - bundled_by (hash) refers to bundle_cart_key of the bundle line.
     * - bundle_cart_key (hash) unique identifier.
     * - bundled_item_hidden: 'yes'|'no' or absent (= 'no').
     *
     * 1) In a 1st pass, we first add bundle meta data to each invoice line that
     *    represents a bundle or bundled item.
     * 2) In a 2nd pass, we group the bundled items as children into the parent
     *    line.
     *
     * @param array|null $invoice
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param \Siel\Acumulus\Invoice\Result $localResult
     *
     * @return array|null
     */
    protected function supportBundleProducts($invoice, BaseSource $invoiceSource, /** @noinspection PhpUnusedParameterInspection */ Result $localResult)
    {
        /** @var \WC_Abstract_Order $shopSource */
        $shopSource = $invoiceSource->getSource();
        /** @var WC_Order_Item_Product[] $items */
        $items = $shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            $bundleId = $item->get_meta('_bundle_cart_key');
            $bundledBy = $item->get_meta('_bundled_by');
            if (!empty($bundleId) || !empty($bundledBy)) {
                $line = &$this->getLineByMetaId($invoice[Tag::Customer][Tag::Invoice][Tag::Line], $item->get_id());
                if ($line !== null) {
                    // Add bundle meta data.
                    if ( !empty($bundleId)) {
                        // Bundle or bundled product.
                        $line[Meta::BundleId] = $bundleId;
                    }
                    if ( !empty($bundledBy)) {
                        // Bundled products only.
                        $line[Meta::BundleParentId] = $bundledBy;
                        $line[Meta::BundleVisible] = $item->get_meta('bundled_item_hidden') !== 'yes';
                    }
                }
            }
        }

        $invoice[Tag::Customer][Tag::Invoice][Tag::Line] = $this->groupBundles($invoice[Tag::Customer][Tag::Invoice][Tag::Line]);

        return $invoice;
    }

    protected function &getLineByMetaId($lines, $id)
    {
        foreach ($lines as &$line) {
            if ($line[Meta::Id] == $id) {
                return $line;
            }
        }
        // Not found: occurs with refunds and a quantity of 0.
        return null;
    }

    /**
     * Groups bundled products into the bundle product.
     *
     * @param array $itemLines
     *   The set of invoice lines that may contain bundle lines and bundled
     *   lines.
     *
     * @return array
     *   The set of item lines but with the lines of bundled items
     *   hierarchically placed in their bundle line.
     */
    protected function groupBundles(array $itemLines)
    {
        $result = array();
        foreach ($itemLines as &$itemLine) {
            if (!empty($itemLine[Meta::BundleParentId])) {
                // Find the parent, note that we expect bundle products to
                // appear before their bundled products, so we can search in
                // $result and have a reference to a line in $result returned!
                $parent = &$this->getParentBundle($result, $itemLine[Meta::BundleParentId]);
                if ($parent !== null) {
                    // Add the bundled product as a child to the bundle.
                    $parent[Meta::ChildrenLines][] = $itemLine;
                } else {
                    // Oops: not found. Store a message in the line meta data
                    // and keep it as a separate line.
                    // @todo: add warning?
                    $itemLine[Meta::BundleParentId] .= ': not found';
                    $result[] = $itemLine;
                }
            } else {
                // Not a bundled product: just add it to the result.
                $result[] = $itemLine;
            }
        }
        return $result;
    }

    /**
     * Searches for, and returns by reference, the parent bundle line.
     *
     * @param array $lines
     *   The lines to search for the parent bundle line.
     * @param $parentId
     *   The meta-bundle-id value to search for.
     *
     * @return array|null
     *   The parent bundle line or null if not found.
     */
    protected function &getParentBundle(array &$lines, $parentId)
    {
        foreach ($lines as &$line) {
            if (!empty($line[Meta::BundleId]) && $line[Meta::BundleId] === $parentId) {
                return $line;
            } elseif (!empty($line[Meta::ChildrenLines])) {
                // Recursively search for the parent bundle.
                $parent = &$this->getParentBundle($line[Meta::ChildrenLines], $parentId);
                if ($parent !== null) {
                    return $parent;
                }
            }
        }
        // Not found.
        return null;
    }

}
