<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As such, the "records" returned
 * here are an array of all metadata values, thus not filtered by Acumulus keys.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel {

  const KEY_ENTRY_ID = '_acumulus_entry_id';
  const KEY_TOKEN = '_acumulus_token';
  const KEY_TYPE = '_acumulus_type';
  const KEY_CREATED = '_acumulus_created';
  const KEY_UPDATED = '_acumulus_updated';

  /**
   * {@inheritdoc}
   */
  public function getByEntryId($entryId) {
    $result = false;
    $metaQuery = array(
      'posts_per_page' => 1,
      'meta_key' => static::KEY_ENTRY_ID,
      'meta_value' => $entryId,
      'meta_compare' => '='
    );
    $posts = query_posts($metaQuery);
    if (!empty($posts)) {
      $post = reset($posts);
      $result = get_post_meta($post->id);
    }
    return $result !== false ? $result : null;
  }

  /**
   * {@inheritdoc}
   */
  public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId) {
    $result = get_post_meta($invoiceSourceId);
    return $result !== false ? $result : null;
  }

  /**
   * {@inheritdoc}
   */
  public function save($invoiceSource, $entryId, $token) {
    $now = $this->sqlNow();
    $orderId = $invoiceSource->getId();
    add_post_meta($orderId, static::KEY_CREATED, $now, true);
    //$exists = add_post_meta($orderId, '_acumulus_created', $now, true) === FALSE;
    return update_post_meta($orderId, static::KEY_ENTRY_ID, $entryId) !== FALSE
      && update_post_meta($orderId, static::KEY_TOKEN, $token) !== FALSE
      && update_post_meta($orderId, static::KEY_TYPE, $invoiceSource->getType(), true) !== FALSE
      && update_post_meta($orderId, static::KEY_UPDATED, $now) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($invoiceSource, $entryId, $token, $created) {
    throw new \BadMethodCallException(__METHOD__ . ' not implemented');
  }

  /**
   * {@inheritdoc}
   */
  protected function update($record, $entryId, $token, $updated) {
    throw new \BadMethodCallException(__METHOD__ . ' not implemented');
  }

  /**
   * {@inheritdoc}
   */
  protected function sqlNow() {
    return current_time('timestamp', true);
  }

  /**
   * {@inheritdoc}
   *
   * We use the WordPress metadata API which is readily available, so nothing
   * has to be done here.
   */
  function install() {
    return true;
  }

  /**
   * {@inheritdoc}
   *
   * We use the WordPress metadata API which is readily available, so nothing
   * has to be done here.
   */
  public function uninstall() {
    // @todo: should we delete all Acumulus metadata (with(out) confirmation)?
    //delete_post_meta_by_key('_acumulus_entry_id'); // etc. for other keys as well.
    return true;
  }

}
