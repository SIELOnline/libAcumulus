<?php
namespace Siel\Acumulus\Magento\Shop;

use Mage;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;
use Siel_Acumulus_Model_Entry;

/**
 * Implements the Magento specific acumulus entry model class.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /** @var Siel_Acumulus_Model_Entry */
    protected $model;

    /**
     * AcumulusEntryModel constructor.
     */
    public function __construct()
    {
        $this->model = Mage::getModel('acumulus/entry');
    }

    /**
     * @return Siel_Acumulus_Model_Entry
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
        /** @var Siel_Acumulus_Model_Entry[] $result */
        $result = $this->getModel()->getResourceCollection()
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
        /** @var Siel_Acumulus_Model_Entry $result */
        $result = $this->getModel()->getResourceCollection()
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
        return $this->getModel()
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
     * Magento has separate install scripts, so nothing has to be done here.
     */
    public function uninstall()
    {
        return true;
    }
}
