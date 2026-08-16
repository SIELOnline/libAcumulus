<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormMapper as BaseFormMapper;

use function in_array;

/**
 * FormMapper maps an Acumulus form definition to a WHMCS form definition.
 *
 * The "Admin Area Content/Output" (via "top menu - Modules - Acumulus") expects rendered
 * HTML, see https://developers.whmcs.com/addon-modules/admin-area-output/ and, as such,
 * will be handled by the {@see \Siel\Acumulus\Whmcs\Helpers\FormRenderer}.
 * However, the inline "Configuration" form for an addon (via "🔧 (Tools) - Settings -
 * Modules - Configure") is based on simple field definitions, see
 * https://developers.whmcs.com/addon-modules/configuration/ : defined types are “text”,
 * “password”, “yesno” (checkboxes), “textarea”, “dropdown” and “radio”. If using the
 * dropdown or radio type, then you must provide an “Options” parameter with comma
 * separated values.
 *
 * Example return field:
 * "option_name" => ["FriendlyName" => "My Label", "Type" => "text", "Size" => "25",
 * "Description" => "My Help", "Default" => "Example", ]
 */
class FormMapper extends BaseFormMapper
{
    private array $formValues;

    /**
     * Maps a set of field definitions.
     *
     * @param Form $form
     *   The Acumulus form-definition to map.
     *
     * @return array[]
     *   The PrestaShop form definition for the given Acumulus form.
     */
    public function map(Form $form): array
    {
        $this->formValues = $form->getFormValues();
        return $this->fields($form->getFields());
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     *   A set of Acumulus form fields.
     *
     * @return array[]
     *   The set of WHMCS field definitions for the given Acumulus form
     *   fields.
     */
    protected function fields(array $fields): array
    {
        $result = [];
        foreach ($fields as $id => $field) {
            if (!isset($field['id'])) {
                $field['id'] = $id;
            }
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            $result += $this->field($field);
        }
        return $result;
    }

    /**
     * Maps a single field definition, possibly a fieldset.
     *
     * @param array $field
     *   An Acumulus form field.
     *
     * @return array[]
     *   The WHMCS field definition(s) for the given Acumulus form field.
     *   If this is a fieldset, an array with multiple field-arrays will be returned,
     *   otherwise an array with 1 field-array.
     */
    protected function field(array $field): array
    {
        if (!empty($field['fields'])) {
            $result = $this->fieldset($field);
        } else {
            $result = $this->element($field);
        }
        return $result;
    }

    /**
     * Maps a fieldset.
     *
     * @param array $field
     *   An Acumulus form fieldset.
     *
     * @return array
     *   The WHMCS field definitions for the fields in the given Acumulus fieldset.
     */
    protected function fieldset(array $field): array
    {
        $result = [];
        foreach ($field['fields'] as $childField) {
            $result += $this->field($childField);
        }
        return $result;
    }

    /**
     * Maps a simple element.
     *
     *  "option_name" => ["FriendlyName" => "My Label", "Type" => "text", "Size" => "25",
     *  "Description" => "My Help", "Default" => "Example", ]
     *
     * @param array $field
     *   An Acumulus form field.
     *
     * @return array
     *   The WHMCS field definition for the given Acumulus form field.
     */
    protected function element(array $field): array
    {
        // @todo: multiple checkboxes should result into multiple fields.
        $result = [
            'Type' => $this->getWhmcsType($field['type']),
            'FriendlyName' => $field['label'] ?? '',
        ];

        // Set allowed attributes and help text.
        if (!empty($field['attributes']['size'])) {
            $result['Size'] = $field['attributes']['size'];
        }
        if (!empty($field['attributes']['rows'])) {
            $result['Rows'] = $field['attributes']['rows'];
        }
        if (!empty($field['attributes']['cols'])) {
            $result['Cols'] = $field['attributes']['cols'];
        }
        if (isset($field['description'])) {
            $result['Description'] = $field['description'];
        }

        // Set options for fields with multiple choices.
        if (in_array($field['type'], ['radio', 'select'])) {
            $result['options'] = $this->getWhmcsOptions($field['options']);
        }

        // Set value.
        $result['Default'] = $this->formValues[$field['name']];

        return [$field['name'] => $result];
    }

    /**
     * Returns the PrestaShop form element type for the given Acumulus type.
     */
    protected function getWhmcsType(string $type): string
    {
        return match ($type) {
            'checkbox' => 'yesno',
            'select' => 'dropdown',
            // 'text', 'password', 'textarea', 'radio', but also 'email', 'date', ...
            default => $type,
        };
    }

    /**
     * Converts a list of Acumulus field options to a list of PrestaShop radio
     * button values.
     */
    protected function getWhmcsOptions(array $options): string
    {
        $result = [];
        foreach ($options as $value => $label) {
            $result[] = "$label ($value)";
        }
        return implode(',', $result);
    }
}
