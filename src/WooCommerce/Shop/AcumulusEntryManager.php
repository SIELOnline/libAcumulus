<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Shop;

use DateTimeInterface;
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
 * In WooCommerce/WordPress the acumulus entries are stored as post-metadata, saving and
 * querying this data is done via the WordPress API, which takes care of sanitizing.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /** @noinspection PhpUndefinedMethodInspection false positive */
    protected function createEntryRecordFromSource(WC_Abstract_Order $source): array
    {
        $entry = [];
        $entry[AcumulusEntry::$keySourceType] = $this->shopObjectToSourceType($source);
        $entry[AcumulusEntry::$keySourceId] = $source->get_id();
        $entry[AcumulusEntry::$keyEntryId] = $source->get_meta(AcumulusEntry::$keyEntryId);
        $entry[AcumulusEntry::$keyToken] = $source->get_meta(AcumulusEntry::$keyToken);
        $entry[AcumulusEntry::$keyCreated] = $source->get_meta(AcumulusEntry::$keyCreated);
        $entry[AcumulusEntry::$keyUpdated] = $source->get_meta(AcumulusEntry::$keyUpdated);
        return $entry;
    }

    /**
     * Helper method that converts a WC object to a source type constant.
     *
     * @noinspection PhpUndefinedMethodInspection false positive
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

    public function getByEntryId(int $entryId): ?AcumulusEntry
    {
        $orders = wc_get_orders(
            [
                'limit' => 1,
                'meta_query' => [
                    [
                        'key' => AcumulusEntry::$keyEntryId,
                        'value' => $entryId,
                        'comparison' => '=',
                    ],
                ]
            ]
        );
        $result = null;
        foreach ($orders as $order) {
            $record = $this->createEntryRecordFromSource($order);
            $result = $this->convertDbResultToAcumulusEntry($record);
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $result;
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $result = null;
        /** @var \WC_Order|\WC_Order_Refund $source */
        $source = $invoiceSource->getShopObject();
        // [SIEL #123927]: EntryId may be null, and that can lead to an
        // incorrect "not found" result: use a key that will never
        // contain a null value.
        /** @noinspection PhpUndefinedMethodInspection false positive */
        if ($source->get_meta(AcumulusEntry::$keyCreated) !== '') {
            // Acumulus metadata found: add source id and type as these
            // are not stored in the metadata.
            $record = $this->createEntryRecordFromSource($source);
            $result = $this->convertDbResultToAcumulusEntry($record, $ignoreLock);
        }
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $result;
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool
    {
        $timestamp = $created->getTimestamp();
        /** @var \WC_Abstract_Order $source */
        $source = $invoiceSource->getShopObject();
        // Add meta data.
        $source->add_meta_data(AcumulusEntry::$keyCreated, $timestamp, true);
        $source->add_meta_data(AcumulusEntry::$keyEntryId, $entryId, true);
        $source->add_meta_data(AcumulusEntry::$keyToken, $token, true);
        $source->add_meta_data(AcumulusEntry::$keyUpdated, $timestamp, true);
        $source->save_meta_data();
        return true;
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool
    {
        /** @var \WC_Abstract_Order $source */
        $source = wc_get_order($entry->getSourceId());
        $source->update_meta_data(AcumulusEntry::$keyEntryId, $entryId);
        $source->update_meta_data(AcumulusEntry::$keyToken, $token);
        $source->update_meta_data(AcumulusEntry::$keyUpdated, $updated->getTimestamp());
        $source->save_meta_data();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry): bool
    {
        /** @var \WC_Abstract_Order $source */
        $source = wc_get_order($entry->getSourceId());
        $source->delete_meta_data(AcumulusEntry::$keyEntryId);
        $source->delete_meta_data(AcumulusEntry::$keyToken);
        $source->delete_meta_data(AcumulusEntry::$keyCreated);
        $source->delete_meta_data(AcumulusEntry::$keyUpdated);
        $source->save_meta_data();
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API, which is readily available, so nothing
     * has to be done here.
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * We use the WordPress metadata API, which is readily available, so nothing
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
