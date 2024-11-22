<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Refund;

use function get_class;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As the metadata is stored in the Order
 * object, we need to pass the Order object when updating or deleting the
 * {@see \Siel\Acumulus\WooCommerce\Shop\AcumulusEntry} data as to not get an outdated
 * in-memory Order object ([SIEL #215828]).
 *
 * SECURITY REMARKS
 * ----------------
 * In WooCommerce/WordPress the acumulus entries are stored as post metadata,
 * saving and querying is done via the WordPress API which takes care of
 * sanitizing.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    public static string $keyEntryId = '_acumulus_entry_id';
    public static string $keyToken = '_acumulus_token';
    // Note: the following 2 meta keys are not actually stored, as the post/order id and
    // post/order type give us that information.
    public static string $keySourceId = '_acumulus_id';
    public static string $keySourceType = '_acumulus_type';
    public static string $keyCreated = '_acumulus_created';
    public static string $keyUpdated = '_acumulus_updated';

    protected function createEntryRecordFromSource(WC_Abstract_Order $source): array
    {
        $entry = [];
        $entry[static::$keySourceType] = $this->shopObjectToSourceType($source);
        $entry[static::$keySourceId] = $source->get_id();
        $entry[static::$keyEntryId] = $source->get_meta(static::$keyEntryId);
        $entry[static::$keyToken] = $source->get_meta(static::$keyToken);
        $entry[static::$keyCreated] = $source->get_meta(static::$keyCreated);
        $entry[static::$keyUpdated] = $source->get_meta(static::$keyUpdated);
        return $entry;
    }

    /**
     * Helper method that converts a WC object to a source type constant.
     */
    protected function shopObjectToSourceType(WC_Abstract_Order $shopObject): string
    {
        if ($shopObject instanceof WC_Order || $shopObject->get_type() === 'shop_order') {
            return Source::Order;
        } elseif ($shopObject instanceof WC_Order_Refund || $shopObject->get_type() === 'shop_order_refund') {
            return Source::CreditNote;
        } else {
            $this->log->error(
                'InvoiceManager::shopOrderToSourceType(%s): unknown order class and type: %s',
                get_class($shopObject),
                $shopObject->get_type()
            );
            return Source::Other;
        }
    }

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        $orders = wc_get_orders(
            [
                'limit' => -1,
                'meta_query' => [
                    [
                        'key' => static::$keyEntryId,
                        'value' => $entryId,
                        'comparison' => '=',
                    ],
                ]
            ]
        );
        $result = [];
        foreach ($orders as $order) {
            $result[] = $this->createEntryRecordFromSource($order);
        }
        return $this->convertDbResultToAcumulusEntries($result);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?BaseAcumulusEntry
    {
        $result = null;
        /** @var \WC_Order|\WC_Order_Refund $source */
        $source = $invoiceSource->getShopObject();
        // [SIEL #123927]: EntryId may be null and that can lead to an
        // incorrect "not found" result: use a key that will never
        // contain a null value.
        if ($source->get_meta(static::$keyCreated) !== '') {
            // Acumulus metadata found: add source id and type as these
            // are not stored in the metadata.
            $record = $this->createEntryRecordFromSource($source);
            $result = $this->convertDbResultToAcumulusEntries([$record], $ignoreLock);
        }
        return $result;
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        $now = $this->sqlNow();
        /** @var \WC_Abstract_Order $source */
        $source = $invoiceSource->getShopObject();
        // Add meta data.
        $source->add_meta_data(static::$keyCreated, $now, true);
        $source->add_meta_data(static::$keyEntryId, $entryId, true);
        $source->add_meta_data(static::$keyToken, $token, true);
        $source->add_meta_data(static::$keyUpdated, $now, true);
        $source->save_meta_data();
        return true;
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        /** @var \WC_Abstract_Order $source */
        $source = $invoiceSource !== null ? $invoiceSource->getShopObject() : wc_get_order($entry->getSourceId());
        $source->update_meta_data(static::$keyEntryId, $entryId);
        $source->update_meta_data(static::$keyToken, $token);
        $source->update_meta_data(static::$keyUpdated, $updated);
        $source->save_meta_data();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        /** @var \WC_Abstract_Order $source */
        $source = $invoiceSource !== null ? $invoiceSource->getShopObject() : wc_get_order($entry->getSourceId());
        $source->delete_meta_data(static::$keyEntryId);
        $source->delete_meta_data(static::$keyToken);
        $source->delete_meta_data(static::$keyCreated);
        $source->delete_meta_data(static::$keyUpdated);
        $source->save_meta_data();
        return true;
    }

    protected function sqlNow(): int
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
        //$postId = $entry->getSourceId();
        ///** @var \WC_Abstract_Order $source */
        //$source = wc_get_order($postId);
        //$source->delete_meta_data(static::$keyEntryId); // for other keys as well.
        return true;
    }
}
