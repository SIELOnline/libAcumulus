<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

/**
 * Provides Magento specific form helper features.
 */
class FormHelper extends BaseFormHelper
{
    /**
     * {@inheritdoc}
     *
     * Magento places (checked) checkboxes in an array named after the
     * collection name.
     */
    protected function alterPostedValues(array $postedValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if ($fieldMeta->type === 'checkbox') {
                if (isset($postedValues[$fieldMeta->collection]) && is_array($postedValues[$fieldMeta->collection])) {
                    if (in_array($key, $postedValues[$fieldMeta->collection])) {
                        $postedValues[$key] = $fieldMeta->collection;
                    }
                }
            }
        }
        return $postedValues;
    }

    /**
     * {@inheritdoc}
     *
     * Magento places (checked) checkboxes in an array named after the
     * collection name.
     */
    public function alterFormValues(array $formValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if ($fieldMeta->type === 'checkbox') {
                if (!empty($formValues[$key])) {
                    // Check for empty() as the collection name may have
                    // been initialized with an empty string.
                    if (empty($formValues[$fieldMeta->collection])) {
                        $formValues[$fieldMeta->collection] = array();
                    }
                    $formValues[$fieldMeta->collection][] = $key;
                }
            }
        }
        return $formValues;
    }
}
