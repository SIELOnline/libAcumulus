<?php
namespace Siel\Acumulus\Magento\Magento1\Helpers;

use Siel\Acumulus\Magento\Helpers\FormMapper as BaseFormMapper;


/**
 * Class FormMapper maps an Acumulus form definition to a Magento form
 * definition.
 */
class FormMapper extends BaseFormMapper
{
    /**
     * {@inheritdoc}
     */
    protected function getMagentoProperty($key, $value, $type)
    {
        $result = parent::getMagentoProperty($key, $value, $type);

        if ($key === 'type' && $value === 'date') {
            $result['format'] = static::DateFormat;
            $result['image'] = \Mage::getDesign()->getSkinUrl('images/grid-cal.gif');
        }
        return $result;
    }
}
