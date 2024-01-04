<?php

declare(strict_types=1);

namespace Siel\Acumulus\MyWebShop\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the MyWebShop specific acumulus entry manager class.
 *
 * @todo: from the following list:
 * - Define the connection between this library and MyWebShop's database
 *   (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
 * - Implement the retrieval methods getByEntryId() and getByInvoiceSource().
 * - Implement the methods insert() and update(). NOTE: follow MyWebShop's
 *   practices regarding quoting or escaping!
 * - Implement the install() and uninstall() methods that creates or drops the
 *   table. If MyWebShop expects you to define install and uninstall scripts
 *   in a separate well-defined place, do so over there and have these methods
 *   just return true.
 *
 * SECURITY REMARKS
 * ----------------
 * @todo: document why this class is considered safe.
 *   Below is sample text from the PrestaShop module, so do not leave as is.
 * In MyWebShop saving and querying acumulus entries is done via self
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
    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        // @todo: Define the connection between this library and MyWebShop's
        //   database (e.g. OpenCart, PrestaShop) or model architecture (e.g.
        //   Magento).
    }

    public function getByEntryId(?int $entryId)
    {
        // @todo: provide implementation.
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        // @todo: provide implementation.
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, $created): bool
    {
        // @todo: insert a new entry (note that save() takes care of distinguishing between insert and update).
    }

    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, $updated, ?Source $invoiceSource = null): bool
    {
        // @todo: update an existing entry (note that save() takes care of distinguishing between insert and update).
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        // @todo: delete an existing entry.
    }

    protected function sqlNow()
    {
        return date(Api::Format_TimeStamp);
    }

    public function install(): bool
    {
        // @todo: adapt to the way MyWebShop lets you define tables. Just return
        // true if this is done in a separate script.
        /** @noinspection SqlNoDataSourceInspection */
        return $this->getDb()->execute("CREATE TABLE IF NOT EXISTS `$this->tableName` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` int(11) UNSIGNED NOT NULL DEFAULT '1',
        `id_shop_group` int(11) UNSIGNED NOT NULL DEFAULT '1',
        `id_entry` int(11) UNSIGNED DEFAULT NULL,
        `token` char(32) DEFAULT NULL,
        `source_type` varchar(32) NOT NULL,
        `source_id` int(11) UNSIGNED NOT NULL,
        `created` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated` timestamp NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE INDEX `acumulus_idx_entry_id` (`id_entry`),
        UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
        )");
    }

    public function uninstall(): bool
    {
        // @todo: adapt to the way MyWebShop lets you delete tables. Just return true if this is done in a separate script.
        return $this->getDb()->execute("DROP TABLE `$this->tableName`");
    }

    /**
     * Wrapper method around the Db instance.
     *
     * @return \Db
     */
    protected function getDb(): Db
    {
        return Db::getInstance();
    }
}
