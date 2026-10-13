<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use function in_array;

/**
 * FormHandlingTrait provides WHMCS specific handling for forms.
 *
 * - Remove hidden (settings/mappings) fields: as WHMCS is not a general webshop but
 *   specialised in selling hosting and domain services, not all settings make sense.
 *   So those that do not make sense or can get a default value that is not to be
 *   overruled, are removed from the form.
 */
trait FormHandlingTrait
{
    /**
     * Removes hidden fields from a set of form fields.
     *
     * @param array[] $fields
     *   List of form fields keyed by field name.
     * @param string[] $hiddenFields
     *   List of field names that are to be hidden (i.e. removed from $fields).
     */
    protected function removeHiddenFields(array $fields, array $hiddenFields): array
    {
        $result = [];
        foreach ($fields as $id => $field) {
            if (!in_array($id, $hiddenFields, true)) {
                if (isset($field['fields'])) {
                    $field['fields'] = $this->removeHiddenFields($field['fields'], $hiddenFields);
                } elseif ($field['type'] === 'checkbox') {
                    // Remove individual checkboxes that are in the $hiddenFields list.
                    $field['options'] = array_filter($field['options'], static function ($optionId) use ($hiddenFields) {
                        return !in_array($optionId, $hiddenFields, true);
                    }, ARRAY_FILTER_USE_KEY);
                }
                $result[$id] = $field;
            }
        }
        return $result;
    }
}
