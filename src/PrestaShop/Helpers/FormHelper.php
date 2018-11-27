<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;
use Tools;

/**
 * PrestaShop override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /** @var string */
    protected $moduleName = 'acumulus';

    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submitAdd') || Tools::isSubmit('submit' . $this->moduleName);
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop requires field sets at the top level: t the meta field to the
     * first field set.
     */
    public function addMetaField(array $fields)
    {
        $fields = parent::addMetaField($fields);
        foreach ($fields as $key => &$field) {
            if ($field['type'] === 'fieldset') {
                $field['fields'][static::Meta] = $fields[static::Meta];
                unset($fields[static::Meta]);
                break;
            }
        }
        return $fields;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop prepends checkboxes with their collection name.
     */
    protected function alterPostedValues(array $postedValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if ($fieldMeta->type === 'checkbox') {
                $prestaShopName = $fieldMeta->collection . '_' . $key;
                if (isset($postedValues[$prestaShopName])) {
                    $postedValues[$key] = $postedValues[$prestaShopName];
                    unset($postedValues[$prestaShopName]);
                }
            }
        }
        return $postedValues;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop prepends checkboxes with their collection name.
     */
    public function alterFormValues(array $formValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if ($fieldMeta->type === 'checkbox') {
                if (isset($formValues[$key])) {
                    $prestaShopName = $fieldMeta->collection . '_' . $key;
                    $formValues[$prestaShopName] = $formValues[$key];
                }
            }
        }
        return $formValues;
    }
}
