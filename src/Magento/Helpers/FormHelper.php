<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

use function in_array;
use function is_array;

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
    protected function alterPostedValues(array $postedValues): array
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if (($fieldMeta->type === 'checkbox')
                && isset($postedValues[$fieldMeta->collection])
                && is_array($postedValues[$fieldMeta->collection])
                // @todo: should 3rd parameter be false?
                && in_array($key, $postedValues[$fieldMeta->collection], false)
            ) {
                $postedValues[$key] = $fieldMeta->collection;
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
    public function alterFormValues(array $formValues): array
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if (($fieldMeta->type === 'checkbox')
                && !empty($formValues[$key])
            ) {
                // Check for empty() as the collection name may have
                // been initialized with an empty string.
                if (empty($formValues[$fieldMeta->collection])) {
                    $formValues[$fieldMeta->collection] = [];
                }
                $formValues[$fieldMeta->collection][] = $key;
            }
        }
        return $formValues;
    }
}
