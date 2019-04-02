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

    /** @var \Siel_Acumulus_Model_Resource_Entry|\Siel\AcumulusMa2\Model\ResourceModel\Entry */
    protected $resourceModel;

    /**
     * @return \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry
     */
    protected function getModel()
    {
        return $this->model;
    }

    /**
     * @return \Siel_Acumulus_Model_Resource_Entry|\Siel\AcumulusMa2\Model\ResourceModel\Entry
     */
    protected function getResourceModel()
    {
        return $this->resourceModel;
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
    public function getByInvoiceSource(Source $invoiceSource, $ignoreLock = true)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $result */
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this
            ->getModel()
            ->getResourceCollection()
            ->addFieldToFilter('source_type', $invoiceSource->getType())
            ->addFieldToFilter('source_id', $invoiceSource->getId())
            ->getItems();
        return $this->convertDbResultToAcumulusEntries($result, $ignoreLock);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $record = $this
            ->getModel()
            ->setEntryId($entryId)
            ->setToken($token)
            ->setSourceType($invoiceSource->getType())
            ->setSourceId($invoiceSource->getId())
            ->setUpdated($created
            );
        return $this->getResourceModel()->save($record);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function update(BaseAcumulusEntry $entry, $entryId, $token, $updated)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $record */
        $record = $entry
            ->getRecord()
            ->setEntryId($entryId)
            ->setToken($token)
            ->setUpdated($updated);
        return $this->getResourceModel()->save($record);
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry)
    {
        $result = true;
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $record */
        $record = $entry->getRecord();
        try {
            $this->getResourceModel()->delete($record);
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
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
