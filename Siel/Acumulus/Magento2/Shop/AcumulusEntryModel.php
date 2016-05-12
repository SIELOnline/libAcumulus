<?php
namespace Siel\Acumulus\Magento2\Shop;

use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the Magento 2 specific acumulus entry model class.
 * 
 * This class is a bridge between the Acumulus library and the way that Magento
 * 2 models are modelled.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /** @var \Siel\AcumulusMa2\Model\Entry */
    protected $model;

    /**
     * AcumulusEntryModel constructor.
     */
    public function __construct()
    {
        $this->model = Registry::getInstance()
                               ->get('Siel\AcumulusMa2\Model\Entry');
    }

    /**
     * @return \Siel\AcumulusMa2\Model\Entry
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
        return $this->getModel()->load($entryId, 'entry_id');
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $result */
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
