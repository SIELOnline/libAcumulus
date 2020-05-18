<?php
namespace Siel\Acumulus\TestWebShop\Shop;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the TestWebShop specific acumulus entry manager class.
 *
 * @todo:
 * - Define the connection between this library and TestWebShop's database
 *   (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
 * - Implement the retrieval methods getByEntryId() and getByInvoiceSource().
 * - Implement the methods insert() and update(). NOTE: follow TestWebShop's
 *   practices regarding quoting or escaping!
 * - Implement the install() and uninstall() methods that creates or drops the
 *   table. If TestWebShop expects you to define install and uninstall scripts
 *   in a separate well-defined place, do so over there and have these methods
 *   just return true.
 *
 * SECURITY REMARKS
 * ----------------
 * @todo: document why this class is considered safe. Below is sample text from the PrestaShop module, so do not leave as is.
 * In TestWebShop saving and querying acumulus entries is done via self
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
    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        // @todo: Define the connection between this library and TestWebShop's database (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource, $ignoreLock = true)
    {
        // @todo
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        // @todo: insert a new entry (note that save() takes care of distinguishing between insert and update).
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $entry, $entryId, $token, $updated)
    {
        // @todo: update an existing entry (note that save() takes care of distinguishing between insert and update).
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry)
    {
        // @todo: delete an existing entry.
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return date(PluginConfig::TimeStampFormat_Sql);
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        // @todo: adapt to the way TestWebShop lets you define tables. Just return true if this is done in a separate script.
        return $this->getDb()->execute("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
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

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        // @todo: adapt to the way TestWebShop lets you delete tables. Just return true if this is done in a separate script.
        return $this->getDb()->execute("DROP TABLE `{$this->tableName}`");
    }

    /**
     * Wrapper method around the Db instance.
     *
     * @return \Db
     */
    protected function getDb()
    {
        return Db::getInstance();
    }
}
