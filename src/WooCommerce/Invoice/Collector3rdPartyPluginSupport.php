<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use WC_Abstract_Order;
use WC_Booking;
use WC_Booking_Data_Store;
use WC_Order_Item_Product;

use function function_exists;
use function is_string;

/**
 * Collector3rdPartyPluginSupport contains support for features added by other plugins.
 *
 * The WooCommerce market contains many additional plugins that add features to standard
 * WooCommerce. Supporting all these plugins is difficult, and results in hard to read and
 * maintain code. Therefore, we try to split support for these other plugins off into its
 * own containers that react to the Acumulus events (actions).
 */
class Collector3rdPartyPluginSupport
{
    protected function getContainer(): Container
    {
        return Container::getContainer();
    }

    /**
     * See {@see \Siel\Acumulus\Helpers\Event::triggerLineCollectBefore()}
     */
    public function lineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        if ($line->getType() === LineType::Item) {
            $this->itemLineCollectBeforeBookings($propertySources);
        }
    }

    /**
     * See {@see \Siel\Acumulus\Helpers\Event::triggerLineCollectAfter()}
     */
    public function lineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        if ($line->getType() === LineType::Item) {
            $this->itemLineCollectAfterBookings($propertySources);
        }
    }

    /**
     * See {@see \Siel\Acumulus\Helpers\Event::triggerInvoiceCollectAfter()}
     */
    public function acumulusInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource/*, InvoiceAddResult $localResult*/): void
    {
        $this->supportBundleProducts($invoice, $invoiceSource);
        $this->supportTMExtraProductOptions($invoice, $invoiceSource);
    }

    /**
     * Support for the "WooCommerce Bookings" plugin.
     *
     * Bookings are stored in a separate entity, we add that as a separate property
     * source, so its properties can be used.
     */
    public function itemLineCollectBeforeBookings(PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\WooCommerce\Invoice\Item $item */
        $item = $propertySources->get('item');
        if (($item->getProduct() !== null) && function_exists('is_wc_booking_product')) {
            $product = $item->getProduct()->getShopObject();
            if (is_wc_booking_product($product)) {
                $booking_ids = WC_Booking_Data_Store::get_booking_ids_from_order_item_id($item->getId());
                if ($booking_ids) {
                    // I cannot imagine multiple bookings belonging to the same order
                    // line, but if that occurs, only the 1st booking will be added as a
                    // property source.
                    $booking = new WC_Booking(reset($booking_ids));
                    $propertySources->add('booking', $booking);
                    $resource = $booking->get_resource();
                    if ($resource) {
                        $propertySources->add('resource', $resource);
                    }
                }
            }
        }
    }

    /**
     * Supports the "WooCommerce Bookings" plugin.
     *
     * Removes the property sources.
     */
    public function itemLineCollectAfterBookings(PropertySources $propertySources): void
    {
        $propertySources->remove('resource');
        $propertySources->remove('booking');
    }

    /**
     * Supports the "WooCommerce Bundle Products" plugin.
     *
     * This method supports the woocommerce-product-bundles extension. That extension
     * stores the bundle products as separate item lines. This method hierarchically
     * groups bundled products into the bundle product and does so multi-level. Building
     * this hierarchy is done based on metadata:
     * - On the bundle lines:
     *   - 'bundle_cart_key' (hash): unique identifier.
     *   - 'bundled_items' (hash[]): refers to the 'bundle_cart_key' of the bundled
     *     products.
     * - Metadata on bundled items:
     *   - 'bundled_by' (hash): refers to the 'bundle_cart_key' of the bundle line.
     *   - 'bundle_cart_key' (hash): unique identifier.
     *   - 'bundled_item_hidden': 'yes'|'no' or absent (= 'no').
     *
     * How do we build the hierarchy;
     * 1) In a 1st pass, we add bundle metadata to each invoice line that represents a
     *    bundle or bundled item.
     * 2) In a 2nd pass, we group the bundled items as children into the parent line.
     */
    protected function supportBundleProducts(Invoice $invoice, Source $invoiceSource): void
    {
        /** @var \WC_Abstract_Order $shopSource */
        $shopSource = $invoiceSource->getShopObject();
        $this->addBundleMetadata($shopSource, $invoice);
        $invoice->replaceLines($this->groupBundles($invoice->getLines()));
    }

    /**
     * Adds bundle metadata to our invoice lines, so we can group them by bundle.
     */
    protected function addBundleMetadata(WC_Abstract_Order $shopSource, Invoice $invoice): void
    {
        /** @var WC_Order_Item_Product[] $items */
        $items = $shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            $bundleId = $item->get_meta('_bundle_cart_key');
            $bundledBy = $item->get_meta('_bundled_by');
            if (!empty($bundleId) || !empty($bundledBy)) {
                $line = $this->getLineByMetaId($invoice->getLines(), $item->get_id());
                if ($line !== null) {
                    // Add bundle meta data.
                    if (!empty($bundleId)) {
                        // Bundle or bundled product.
                        $line->metadataSet(Meta::BundleId, $bundleId);
                    }
                    if (!empty($bundledBy)) {
                        // Bundled products only.
                        $line->metadataSet(Meta::BundleParentId, $bundledBy);
                        $line->metadataSet(Meta::BundleVisible, $item->get_meta('bundled_item_hidden') !== 'yes');
                    }
                }
            }
        }
    }

    /**
     * Groups bundled products into the bundle product.
     *
     * @param Line[] $lines
     *   The set of invoice lines that may contain bundle lines and bundled lines.
     *
     * @return Line[]
     *   The set of invoice lines but with the lines of bundled items hierarchically
     *   placed on their bundle line.
     */
    protected function groupBundles(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            if ($line->metadataExists(Meta::BundleParentId)) {
                // Find the parent.
                $parent = $this->getParentBundle($lines, $line->metadataGet(Meta::BundleParentId));
                if ($parent !== null) {
                    // Add the bundled product as a child to the bundle.
                    $parent->addChild($line);
                } else {
                    // Oops: not found. Add a warning in the line metadata and keep it as
                    // a separate line.
                    $line->addWarning('Bundle parent with id=' . $line->metadataGet(Meta::BundleParentId) . ' not found');
                    $result[] = $line;
                }
            } else {
                // Not a bundled product: just add it to the result.
                $result[] = $line;
            }
        }
        return $result;
    }

    /**
     * Searches for, and returns by reference, the parent bundle line.
     *
     * @param Line[] $lines
     *   The lines to search for the parent bundle line.
     * @param int|string $parentId
     *   The meta-bundle-id value to search for.
     *
     * @return null|Line
     *   The parent bundle line or null if not found.
     */
    protected function getParentBundle(array $lines, int|string $parentId): ?Line
    {
        foreach ($lines as $line) {
            if ($line->metadataGet(Meta::BundleId) === $parentId) {
                return $line;
            } elseif ($line->hasChildren()) {
                // Recursively search for the parent bundle.
                $parent = $this->getParentBundle($line->getChildren(), $parentId);
                if ($parent !== null) {
                    return $parent;
                }
            }
        }
        return null;
    }

    /**
     * Supports the "WooCommerce TM Extra Product Options" plugin.
     *
     * This method supports the tm-woo-extra-product-options extension that places its
     * data in the metadata under keys that start with 'tm_epo' or 'tmcartepo'.
     * We need the 'tmcartepo_data' value as that contains the options.
     *
     * This method adds the option data as children to the invoice line.
     */
    protected function supportTMExtraProductOptions(Invoice $invoice, Source $invoiceSource): void
    {
        /** @var \WC_Abstract_Order $shopSource */
        $shopSource = $invoiceSource->getShopObject();
        /** @var WC_Order_Item_Product[] $items */
        $items = $shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            // If the plugin is no longer used, we may still have an order with products
            // where the plugin was used. Moreover, we don't use any function or method
            // from the plugin, only its stored data. So we do not have to check for the
            // plugin being active, just for the data being there.
            if (!empty($item->get_meta('tmcartepo_data'))) {
                $line = $this->getLineByMetaId($invoice->getLines(), $item->get_id());
                if ($line !== null) {
                    $children = $this->getExtraProductOptionsLines($item);
                    foreach ($children as $child) {
                        $child->metadataSet(Meta::SubType, LineType::Item);
                        $child->quantity = $line->quantity;
                        $child->unitPrice = 0.0;
                        $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                        $line->addChild($child);
                    }
                }
            }
        }
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * @param \WC_Order_Item_Product $item
     *   An item line with 'tmcartepo_data' metadata.
     *
     * @return Line[]
     *   An array of lines that describes this variant.
     */
    protected function getExtraProductOptionsLines(WC_Order_Item_Product $item): array
    {
        $result = [];

        // It is a bit unclear what format this metadata should have. In old versions I
        // had an unconditional unserialize(), but now I get an array of options (being
        // arrays themselves) and code of the plugin itself expects an array that may
        // contain serialized values, i.e. it uses maybe_unserialize() on the elements,
        // not on the complete value.
        $options = $item->get_meta('tmcartepo_data');
        if (is_string($options)) {
            $options = (array) maybe_unserialize($options);
        } else {
            array_walk($options, static function (&$value) {
                if (is_string($value)) {
                    $value = maybe_unserialize($value);
                }
            });
        }

        foreach ($options as $option) {
            // Get option name and choice.
            $label = $option['name'];
            $choice = $option['value'];

            /** @var Line $line */
            $line = $this->getContainer()->createAcumulusObject(DataType::Line);
            $line->product = $label . ': ' . $choice;
            $result[] = $line;
        }
        return $result;
    }

    /**
     * @param Line[] $lines
     */
    protected function getLineByMetaId(array $lines, int $id): ?Line
    {
        foreach ($lines as $line) {
            if ($line->metadataGet(Meta::Id) === $id) {
                return $line;
            }
        }
        // Not found: occurs with refunds and a quantity of 0.
        return null;
    }
}
