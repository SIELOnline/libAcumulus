<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides PrestaShop specific handling for the Batch form.
 */
class BatchForm extends BaseBatchForm
{
    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();
        $result['batchFields']['icon'] = 'icon-exchange';
        if (isset($result['batchLog'])) {
            $result['batchLog']['icon'] = 'icon-list';
        }
        $result['batchInfo']['icon'] = 'icon-info-circle';
        $result['versionInformation']['icon'] = 'icon-info-circle';
        return $result;
    }
}
