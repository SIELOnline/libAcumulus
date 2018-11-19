<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the VirtueMart Acumulus module.
 */
class BatchForm extends BaseBatchForm
{
    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();
        $result['batchFieldsHeader']['icon'] = 'icon-exchange';
        if (isset($result['batchLogHeader'])) {
            $result['batchLogHeader']['icon'] = 'icon-list';
        }
        $result['batchInfoHeader']['icon'] = 'icon-info-circle';
        return $result;
    }
}
