<?php
namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use JDate;
use JFactory;
use JTable;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the VirtueMart specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In Joomla (VirtueMart/HikaShop) saving and querying acumulus entries is done
 * via the Joomla table classes which take care of sanitizing.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /**
     * @return \AcumulusTableAcumulusEntry
     */
    protected function newTable()
    {
        /**
         * @var bool|\AcumulusTableAcumulusEntry $table
         */
        $table = JTable::getInstance('AcumulusEntry', 'AcumulusTable');
        if ($table === false) {
            $this->log->error('AcumulusEntryManager::newTable(): table not created');
        }
        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $table = $this->newTable();
        $result = $table->loadMultiple(array('entry_id' => $entryId));
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource)
    {
        $table = $this->newTable();
        $result = $table->load(array('source_type' => $invoiceSource->getType(), 'source_id' => $invoiceSource->getId()), true);
        return $result ? $this->convertDbResultToAcumulusEntries($table) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        // Start with new table class to not overwrite any loaded record.
        $table = $this->newTable();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->source_type = $invoiceSource->getType();
        $table->source_id = $invoiceSource->getId();
        $table->created = $created;
        $table->updated = $created;
        return $table->store();
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $record, $entryId, $token, $updated)
    {
        // Continue with existing table object with already loaded record.
        /** @var \AcumulusTableAcumulusEntry $table */
        $table = $record->getRecord();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->updated = $updated;
        return $table->store(false);
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $tz = new DateTimeZone(JFactory::getApplication()->get('offset'));
        $date = new JDate();
        $date->setTimezone($tz);
        return $date->toSql(true);
    }

    /**
     * {@inheritdoc}
     *
     * Joomla has separate install scripts, so nothing has to be done here.
     */
    public function install()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Joomla has separate install scripts, so nothing has to be done here.
     */
    public function uninstall()
    {
        return false;
    }
}
