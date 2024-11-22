<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Exception;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In PrestaShop saving and querying acumulus entries is done via self
 * constructed queries, therefore this class takes care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using pSQL(), unless they are hard coded or are
 *   internal variables.
 * Note that:
 * - $invoiceSource, $created and $updated are set in calling code, and can
 *   thus be considered trusted, but are still escaped or cast.
 * - $entryId and $token come from outside, from the Acumulus API, and must
 *   thus be handled as untrusted.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    protected string $tableName;

    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
    }

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        $operator = $entryId === null ? 'is' : '=';
        $value = $entryId === null ? 'null' : (string) $entryId;
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS("SELECT * FROM `$this->tableName` WHERE id_entry $operator $value");
        return $this->convertDbResultToAcumulusEntries($result);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?BaseAcumulusEntry
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS(sprintf(
            "SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u",
            $this->tableName,
            pSQL($invoiceSource->getType()),
            $invoiceSource->getId()
        ));
        return $this->convertDbResultToAcumulusEntries($result, $ignoreLock);
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        if ($invoiceSource->getType() === Source::Order) {
            $shopId = $invoiceSource->getShopObject()->id_shop;
            $shopGroupId = $invoiceSource->getShopObject()->id_shop_group;
        } else {
            $shopId = 0;
            $shopGroupId = 0;
        }
        return $this->getDb()->execute(sprintf(
            "INSERT INTO `%s` (id_shop, id_shop_group, id_entry, token, source_type, source_id, updated) VALUES (%u, %u, %s, %s, '%s', %u, '%s')",
            $this->tableName,
            $shopId,
            $shopGroupId,
            $entryId === null ? 'null' : (string) $entryId,
            $token === null ? 'null' : ("'" . pSQL($token) . "'"),
            pSQL($invoiceSource->getType()),
            $invoiceSource->getId(),
            pSQL($created)
        ));
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        $record = $entry->getRecord();
        return $this->getDb()->execute(sprintf(
            "UPDATE `%s` SET id_entry = %s, token = %s, updated = '%s' WHERE id = %u",
            $this->tableName,
            $entryId === null ? 'null' : (string) $entryId,
            $token === null ? 'null' : ("'" . pSQL($token) . "'"),
            pSQL($updated),
            $record['id']
        ));
    }

    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        $record = $entry->getRecord();
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->getDb()->execute(sprintf(
            'DELETE FROM `%s` WHERE id = %u',
            $this->tableName,
            $record['id']
        ));
    }

    protected function sqlNow()
    {
        return date(Api::Format_TimeStamp);
    }

    /**
     * {@inheritdoc}
     *
     * Creates the acumulus_entry table.
     *
     * For some background info about 2 timestamp columns see:
     * - {@link https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-5.html#mysqld-5-6-5-data-types}.
     * - {@link https://dev.mysql.com/doc/refman/5.6/en/timestamp-initialization.html}.
     * - {@link https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html#sqlmode_no_zero_date}.
     *
     * @return bool
     *   Success.
     *
     * @throws Exception
     */
    public function install(): bool
    {
        return $this->getDb()->execute(
        "CREATE TABLE IF NOT EXISTS `$this->tableName` (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_shop` int(11) UNSIGNED NOT NULL DEFAULT '1',
            `id_shop_group` int(11) UNSIGNED NOT NULL DEFAULT '1',
            `id_entry` int(11) UNSIGNED DEFAULT NULL,
            `token` char(32) DEFAULT NULL,
            `source_type` varchar(32) NOT NULL,
            `source_id` int(11) UNSIGNED NOT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `acumulus_idx_entry_id` (`id_entry`),
            UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
            )"
        );
    }

    public function uninstall(): bool
    {
        return $this->getDb()->execute("DROP TABLE `$this->tableName`");
    }

    /**
     * Wrapper method around the Db instance.
     */
    protected function getDb(): Db
    {
        return Db::getInstance();
    }
}
