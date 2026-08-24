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
 *
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
 *
 * We do not really use that part, but this mapper was easy enough to develop, so we can
 * use some simple form fields over there.
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
     *   The WHMCS form definition for the given Acumulus form.
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
     *   If this is a fieldset or a checkbox element, an array with multiple field-arrays
     *   will be returned, otherwise an array with 1 field-array.
     */
    protected function field(array $field): array
    {
        if (!empty($field['fields'])) {
            return $this->fieldset($field);
        } elseif ($field['type'] === 'checkbox') {
            return $this->element($field);
        } else {
            return $this->element($field);
        }
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
        return $this->fields($field['fields']);
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
        } elseif ($field['type'] === 'checkbox') {
            // Ignore multiple options, we only use this mapper on the simple inline
            // config form, so we don't bother with all possible cases.
            $result['FriendlyName'] = reset($field['options']);
        }

        // Set value.
        $result['Default'] = $this->formValues[$field['name']];

        return [$field['name'] => $result];
    }

    /**
     * Returns the WHMCS form element type for the given Acumulus type.
     */
    protected function getWhmcsType(string $type): string
    {
        return match ($type) {
            'checkbox' => 'yesno',
            'radio' => 'radio',
            'select' => 'dropdown',
            'password' => 'password',
            'textarea' => 'textarea',
            default => 'text',
        };
    }

    /**
     * Converts a list of Acumulus field options to a list of WHMCS radio
     * button values.
     *
     * The WHMCS form field definition does not allow for separate values and labels.
     * As this is only used on the inline config forms on the Modules page, we don't
     * bother about this not being able to cater for all features that our form field
     * specification language uses.
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
