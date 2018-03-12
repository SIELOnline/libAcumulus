<?php
namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the Magento specific acumulus entry model class.
 *
 * This class is a bridge between the Acumulus library and the way that Magento
 * models are modelled.
 *
 * SECURITY REMARKS
 * ----------------
 * In Magento saving and querying acumulus entries is done via the Magento DB
 * API which takes care of sanitizing.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry */
    protected $model;

    /**
     * @return \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry
     */
    protected function getModel()
    {
        return $this->model;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        /** @var \Siel_Acumulus_Model_Entry[]|\Siel\AcumulusMa2\Model\Entry[] $result */
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this
           ->getModel()
           ->getResourceCollection()
           ->addFieldToFilter('entry_id', $entryId)
           ->getItems();
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $result */
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this
            ->getModel()
            ->getResourceCollection()
            ->addFieldToFilter('source_type', $invoiceSource->getType())
            ->addFieldToFilter('source_id', $invoiceSource->getId())
            ->getFirstItem();
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this
            ->getModel()
            ->setEntryId($entryId)
            ->setToken($token)
            ->setSourceType($invoiceSource->getType())
            ->setSourceId($invoiceSource->getId())
            ->save();
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $record, $entryId, $token, $updated)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $entry */
        $entry = $record->getRecord();
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        /** @noinspection PhpUnhandledExceptionInspection */
        return $entry
            ->setEntryId($entryId)
            ->setToken($token)
            ->save();
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return time();
    }

    /**
     * {@inheritdoc}
     *
     * Magento has separate install scripts, so nothing has to be done here.
     */
    public function install()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Magento has separate uninstall scripts, so nothing has to be done here.
     */
    public function uninstall()
    {
        return true;
    }
}
