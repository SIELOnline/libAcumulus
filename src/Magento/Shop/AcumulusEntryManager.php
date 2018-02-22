<?php
namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the Magento specific acumulus entry model class.
 *
 * This class is a bridge between the Acumulus library and the way that Magento
 * models are modelled.
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
        $result = $this
           ->getModel()
           ->getResourceCollection()
           ->addFieldToFilter('entry_id', $entryId)
           ->getItems();
        $result = count($result) === 0 ? null : (count($result) === 1 ? reset($result) : $result);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $result */
        $result = $this
            ->getModel()
            ->getResourceCollection()
            ->addFieldToFilter('source_type', $invoiceSourceType)
            ->addFieldToFilter('source_id', $invoiceSourceId)
            ->getFirstItem();
        return $result->getSourceId() == $invoiceSourceId ? $result : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert($invoiceSource, $entryId, $token, $created)
    {
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
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
    protected function update($record, $entryId, $token, $updated)
    {
        return $record
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
     */
    public function getField($record, $field)
    {
        /** @var \Siel_Acumulus_model_Entry $record */
        return $record->getData($field);
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
