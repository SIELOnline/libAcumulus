<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use WP_Query;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As such, the "records" returned
 * here are an array of all metadata values, thus not filtered by Acumulus keys.
 *
 * SECURITY REMARKS
 * ----------------
 * In WooCommerce/WordPress the acumulus entries are stored as post metadata,
 * saving and querying is done via the WordPress API which takes care of
 * sanitizing.
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
    protected function shopTypeToSourceType(string $shopType): string
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
        $metaQuery = [
            'posts_per_page' => 1,
            'meta_key' => static::$keyEntryId,
            'meta_value' => $entryId,
            'meta_compare' => '=',
        ];
        $query = new WP_Query();
        $posts = $query->query($metaQuery);
        $result = [];
        foreach ($posts as $post) {
            $result1 = get_post_meta($post->ID);
            $result1[static::$keySourceType] = $this->shopTypeToSourceType($post->post_type);
            $result1[static::$keySourceId] = $post->ID;
            $result[] = $result1;
        }
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource, $ignoreLock = true)
    {
        $result = null;
        $invoiceSourceType = $invoiceSource->getType();
        $invoiceSourceId = (int) $invoiceSource->getId();
        $post = get_post($invoiceSourceId);
        if (!empty($post)) {
            if ($this->shopTypeToSourceType($post->post_type) === $invoiceSourceType) {
                $postMeta = get_post_meta($invoiceSourceId);
                // [SIEL #123927]: EntryId may be null and that can lead to an
                // incorrect "not found" result: use a key that will never
                // contain a null value.
                if (isset($postMeta[static::$keyCreated])) {
                    // Acumulus metadata found: add source id and type as these
                    // are not stored in the metadata.
                    $postMeta[static::$keySourceType] = $invoiceSourceType;
                    $postMeta[static::$keySourceId] = $invoiceSourceId;
                    $result = $this->convertDbResultToAcumulusEntries([$postMeta], $ignoreLock);
                }
            } else {
                $this->log->error('InvoiceManager::getByInvoiceSource(%s %d): unknown post type %s', $invoiceSourceType, $invoiceSourceId, empty($post->post_type) ? 'no post type' : $post->post_type);
            }
        } else {
            $this->log->error('InvoiceManager::getByInvoiceSource(%s %d): unknown post', $invoiceSourceType, $invoiceSourceId);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created): bool
    {
        $now = $this->sqlNow();
        $postId = $invoiceSource->getId();
        // Add meta data.
        $result1 = add_post_meta($postId, static::$keyCreated, $now, true);
        $result2 = add_post_meta($postId, static::$keyEntryId, $entryId, true);
        $result3 = add_post_meta($postId, static::$keyToken, $token, true);
        $result4 = add_post_meta($postId, static::$keyUpdated, $now, true);
        return $result1 !== false && $result2 !== false && $result3 !== false && $result4 !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $entry, $entryId, $token, $updated): bool
    {
        $postId = $entry->getSourceId();
        // Overwrite fields. To be able to return a correct success value, we
        // should not update with the same value as that returns false ...!
        if ($entry->getEntryId() !== null) {
            $result1 = $entry->getEntryId() !== $entryId ? update_post_meta($postId, static::$keyEntryId, $entryId) : true;
        } else {
            $result1 = $entry->getConceptId() !== $entryId ? update_post_meta($postId, static::$keyEntryId, $entryId) : true;
        }
        $result2 = $entry->getToken() !== $token ? update_post_meta($postId, static::$keyToken, $token) : true;
        $result3 = $entry->getUpdated(true) != $updated ? update_post_meta($postId, static::$keyUpdated, $updated) : true;
        return $result1 !== false && $result2 !== false && $result3 !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry): bool
    {
        $postId = $entry->getSourceId();
        delete_post_meta($postId, static::$keyEntryId);
        delete_post_meta($postId, static::$keyToken);
        delete_post_meta($postId, static::$keyCreated);
        delete_post_meta($postId, static::$keyUpdated);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return current_time('timestamp', true);
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API which is readily available, so nothing
     * has to be done here.
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API which is readily available, so nothing
     * has to be done here.
     */
    public function uninstall(): bool
    {
        // We do not delete the Acumulus metadata, not even via a confirmation
        // page. If we want to do so, we can use this code:
        // delete_post_meta_by_key('_acumulus_entry_id'); // for other keys as well.
        return true;
    }
}
