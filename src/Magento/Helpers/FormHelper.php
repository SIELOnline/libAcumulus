<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Provides Magento specific form helper features.
 */
class FormHelper extends BaseFormHelper
{
    /**
     * Prefix to add to option values (select or radio) to distinguish them from
     * the empty value in a weak comparison.
     */
    protected const Unique = 'UNIQUE_';

    /**
     * {@inheritdoc}
     *
     * Magento places (checked) checkboxes in an array named after the
     * collection name.
     *
     * Magento cannot handle empty values for radios (or selects?).
     */
    protected function alterPostedValues(array $postedValues): array
    {
        $postedValues = parent::alterPostedValues($postedValues);

        /** @var object[]|null $meta */
        $meta = $this->getMeta();
        foreach ($meta as $key => $fieldMeta) {
            if (($fieldMeta->type === 'checkbox')
                && isset($postedValues[$fieldMeta->collection])
                && is_array($postedValues[$fieldMeta->collection])
                // @todo: should 3rd parameter be false?
                && in_array($key, $postedValues[$fieldMeta->collection], false)
            ) {
                $postedValues[$key] = $fieldMeta->collection;
            }
        }

        array_walk_recursive($postedValues, static function (&$postedValue/*, $key*/) {
            if (is_string($postedValue) && in_array(
                    substr($postedValue, 0, strlen(self::Unique . 'i:')),
                    [self::Unique . 'i:', self::Unique . 's:'],
                    true
                )) {
                $postedValue = unserialize(substr($postedValue, strlen(self::Unique)), ['allowed_classes' => false]);
            }
        });

        return $postedValues;
    }

    /**
     * {@inheritdoc}
     *
     * Magento places (checked) checkboxes in an array named after the
     * collection name.
     *
     * As we changed empty values for fields with options (radio, select), we should do
     * the same for the actual form value.
     */
    public function alterFormValues(array $formValues, array $fields): array
    {
        $formValues = parent::alterFormValues($formValues, $fields);

        foreach ($this->getMeta() as $key => $fieldMeta) {
            /**
             * @var \stdClass $fieldMeta
             *   Having properties 'name', 'type' and, optionally, 'collection'.
             */
            if (($fieldMeta->type === 'checkbox') && !empty($formValues[$key])) {
                // Check for empty() as the collection name may have
                // been initialized with an empty string.
                if (empty($formValues[$fieldMeta->collection])) {
                    $formValues[$fieldMeta->collection] = [];
                }
                $formValues[$fieldMeta->collection][] = $key;
            }

            // Check for isset() as we want to process "empty" values.
            if (($fieldMeta->type === 'radio') && isset($formValues[$key])) {
                if (empty($formValues[$key])) {
                    $formValues[$key] = self::Unique . serialize($formValues[$key]);
                }
            }
        }

        // Change keys from name to id.
        $idKeyedFormValues = [];
        foreach ($formValues as $name => $formValue) {
            $field = $this->getFieldByName($fields, $name);
            $id = $field['id'] ?? $name;
            $idKeyedFormValues[$id] = $formValue;
        }
        return $idKeyedFormValues;
    }

    protected function processField(array $field, string $key): array
    {
        $field = parent::processField($field, $key);

        // Make options unique:
        // - Empty radio button values are not rendered with a radio button in Magento.
        // - Option values may not clash with the "empty value" in a weak comparison
        //   (Magento uses in_array() with the strict parameter set to false).
        if (!empty($field['options'])) {
            $options = [];
            $emptyValue = null;
            foreach ($field['options'] as $id => $label) {
                // The empty value, if any, is the 1st item in the list of options.
                $first = $emptyValue === null;
                if ($first) {
                    $emptyValue = $id;
                }
                if (ctype_digit((string) $id)) {
                    $id = (int) $id;
                }
                /** @noinspection TypeUnsafeComparisonInspection weak comparison required */
                if (empty($id) || (!$first && $id == $emptyValue)) {
                    if (!$first && $id === $emptyValue) {
                        $this->log->warning('%s: option "%s" (label: %s) equals empty option "%s"', __METHOD__, $id, $label, $emptyValue);
                    }
                    $id = self::Unique . serialize($id);
                }
                $options[$id] = $label;
            }
            $field['options'] = $options;
        }

        return $field;
    }

    protected function getFieldByName(array $fields, string $name): ?array
    {
        foreach ($fields as $key => $field) {
            if ($key === $name) {
                return $field;
            }
            if (isset($field['fields'])) {
                $field = $this->getFieldByName($field['fields'], $name);
                if ($field !== null) {
                    return $field;
                }
            }
        }
        return null;
    }
}
