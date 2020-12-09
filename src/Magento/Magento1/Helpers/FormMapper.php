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
    protected function getMagentoProperty(array $config, $key, $value, $type)
    {
        $config = parent::getMagentoProperty($config, $key, $value, $type);

        if ($key === 'type' && $value === 'date') {
            $config['format'] = static::DateFormat;
            $config['image'] = \Mage::getDesign()->getSkinUrl('images/grid-cal.gif');
        }
        return $config;
    }
}
