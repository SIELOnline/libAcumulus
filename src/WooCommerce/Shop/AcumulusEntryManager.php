<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Invoice\Source;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As such, the "records" returned
 * here are an array of all metadata values, thus not filtered by Acumulus keys.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    static public $keyEntryId = '_acumulus_entry_id';
    static public $keyToken = '_acumulus_token';
    // Note: these 2 meta keys are not actually stored as the post_id and
    // post_type give us that information.
    static public $keySourceId = '_acumulus_id';
    static public $keySourceType = '_acumulus_type';
    static public $keyCreated = '_acumulus_created';
    static public $keyUpdated = '_acumulus_updated';

    /**
     * Helper method that converts a WP/WC post type to a source type constant.
     *
     * @param string $shopType
     *
     * @return string
     */
    protected function shopTypeToSourceType($shopType)
    {
        switch ($shopType) {
            case 'shop_order':
                return Source::Order;
            case 'shop_order_refund':
                return Source::CreditNote;
            default:
                $this->log->error('InvoiceManager::shopTypeToSourceType(%s): unknown', $shopType);
                return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $metaQuery = array(
            'posts_per_page' => 1,
            'meta_key' => static::$keyEntryId,
            'meta_value' => $entryId,
            'meta_compare' => '=',
        );
        $posts = query_posts($metaQuery);
        if (!empty($posts)) {
            $result = array();
            foreach ($posts as $post) {
                $result1 = get_post_meta($post->id);
                $result1[static::$keySourceType] = $this->shopTypeToSourceType($post->post_type);
                $result[] = $result1;
            }
            if (count($result) === 1) {
                $result = reset($result);
            }
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        $result = null;
        $post = get_post($invoiceSourceId);
        if (!empty($post->post_type) && $this->shopTypeToSourceType($post->post_type) === $invoiceSourceType) {
            $result = get_post_meta($invoiceSourceId);
            if (array_key_exists(static::$keyEntryId, $result)) {
                // Acumulus meta data found: add source id and type as these are not stored in
                // the meta data.
                $result[static::$keySourceId] = $invoiceSourceId;
                $result[static::$keySourceType] = $invoiceSourceType;
            } else {
                $result = null;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override uses the WordPress meta data API to store the acumulus entry
     * data with the order.
     */
    public function save($invoiceSource, $entryId, $token)
    {
        $now = $this->sqlNow();
        $orderId = $invoiceSource->getId();
        add_post_meta($orderId, static::$keyCreated, $now, true);
        //$exists = add_post_meta($orderId, '_acumulus_created', $now, true) === false;
        return update_post_meta($orderId, static::$keyEntryId, $entryId) !== false
            && update_post_meta($orderId, static::$keyToken, $token) !== false
            && update_post_meta($orderId, static::$keyUpdated, $now) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert($invoiceSource, $entryId, $token, $created)
    {
        throw new \BadMethodCallException(__METHOD__ . ' not implemented');
    }

    /**
     * {@inheritdoc}
     */
    protected function update($record, $entryId, $token, $updated)
    {
        throw new \BadMethodCallException(__METHOD__ . ' not implemented');
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return current_time('timestamp', true);
    }

    /**
     * @inheritDoc
     */
    public function getField($record, $field)
    {
        $result = parent::getField($record, $field);
        if (is_array($result)) {
            $result = reset($result);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API which is readily available, so nothing
     * has to be done here.
     */
    public function install()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API which is readily available, so nothing
     * has to be done here.
     */
    public function uninstall()
    {
        // We do not delete the Acumulus metadata, not even via a confirmation
        // page. If we would want to do so, we can use this code:
        // delete_post_meta_by_key('_acumulus_entry_id'); // for other keys as well.
        return true;
    }
}
