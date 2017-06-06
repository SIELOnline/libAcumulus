<?php
namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use JDate;
use JFactory;
use JTable;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;
use Siel\Acumulus\Joomla\Helpers\Log;

/**
 * Implements the VirtueMart specific acumulus entry model class.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /**
     * @return \AcumulusTableAcumulusEntry
     */
    protected function newTable()
    {
        $table = JTable::getInstance('AcumulusEntry', 'AcumulusTable');
        if ($table === false) {
            $this->log->error('AcumulusEntryModel::newTable(): table not created');
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
        return count($result) === 0 ? null : (count($result) === 1 ? reset($result) : $result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        $table = $this->newTable();
        $result = $table->load(array('source_type' => $invoiceSourceType, 'source_id' => $invoiceSourceId), true);
        return $result ? $table : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert($invoiceSource, $entryId, $token, $created)
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
    protected function update($record, $entryId, $token, $updated)
    {
        // Continue with existing table object with already loaded record.
        $table = $record;
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
