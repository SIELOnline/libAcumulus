<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormMapper as BaseFormMapper;

/**
 * FormMapper maps an Acumulus form definition to a PrestaShop form definition.
 */
class FormMapper extends BaseFormMapper
{
    /**
     * Maps a set of field definitions.
     *
     * @param Form $form
     *   The Acumulus form definition to map.
     *
     * @return array[]
     *   The PrestaShop form definition for the given Acumulus form.
     */
    public function map(Form $form): array
    {
        return $this->fields($form->getFields());
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     *   A set of Acumulus form fields.
     *
     * @return array[]
     *   The set of PrestaShop field definitions for the given Acumulus form
     *   fields.
     */
    protected function fields(array $fields): array
    {
        /** @noinspection DuplicatedCode */
        $result = [];
        foreach ($fields as $id => $field) {
            if (!isset($field['id'])) {
                $field['id'] = $id;
            }
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            $result[$id] = $this->field($field);
        }
        return $result;
    }

    /**
     * Maps a single field definition, possibly a fieldset.
     *
     * @param array $field
     *   An Acumulus form field.
     *
     * @return array
     *   The PrestaShop field definition for the given Acumulus form field.
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
     *   The PrestaShop field definition for the given Acumulus fieldset.
     */
    protected function fieldset(array $field): array
    {
        $result = [
            'form' => [
                'legend' => [
                    'title' => !empty($field['summary']) ? $field['summary'] : $field['legend'],
                ],
                'attributes' => ['class' => 'details'],
                'input' => $this->fields($field['fields']),
            ],
        ];

        // Add description at the start of the fieldset as an HTML element.
        if (isset($field['description'])) {
            array_unshift($result['form']['input'], ['type' => 'html', 'name' => $field['name'] . '_description', 'html_content' => '<div class="help-block">' . $field['description'] . '</div>']);
        }

        // Add icon to legend.
        if (isset($field['icon'])) {
            $result['form']['legend']['icon'] = $field['icon'];
        }
        return $result;
    }

    /**
     * Maps a simple element.
     *
     * @param array $field
     *   An Acumulus form field.
     *
     * @return array
     *   The PrestaShop field definition for the given Acumulus form field.
     */
    protected function element(array $field): array
    {
        $result = [
            'type' => $this->getPrestaShopType($field['type']),
            'label' => $field['label'] ?? '',
            'name' => $field['name'],
            'required' => $field['attributes']['required'] ?? false,
            'multiple' => $field['attributes']['multiple'] ?? false,
        ];

        if (!empty($field['attributes'])) {
            $result['attributes'] = $field['attributes'];
        }
        if (isset($field['description'])) {
            $result['desc'] = $field['description'];
        }

        if ($field['type'] === 'radio') {
            $result['values'] = $this->getPrestaShopValues($field['name'], $field['options']);
        } elseif ($field['type'] === 'checkbox') {
            $result['values'] = $this->getPrestaShopOptions($field['options']);
        } elseif ($field['type'] === 'select') {
            $result['options'] = $this->getPrestaShopOptions($field['options']);
            if ($result['multiple']) {
                $result['size'] = $field['attributes']['size'];
            }
        }

        return $result;
    }

    /**
     * Returns the PrestaShop form element type for the given Acumulus type.
     */
    protected function getPrestaShopType(string $type): string
    {
        switch ($type) {
            case 'fieldset':
            case 'details':
            case 'markup':
                $type = 'free';
                break;
            case 'email':
                $type = 'text';
                break;
            default:
                // Return as is: text, password, textarea, radio, select,
                // checkbox, date. PrestaShop accepts these as are.
                break;
        }
        return $type;
    }

    /**
     * Converts a list of Acumulus field options to a list of PrestaShop radio
     * button values.
     */
    protected function getPrestaShopValues(string $id, array $options): array
    {
        $result = [];
        foreach ($options as $value => $label) {
            $result[] = [
                'id' => $id . $value,
                'value' => $value,
                'label' => $label,
            ];
        }
        return $result;
    }

    /**
     * Converts a list of Acumulus field options to a set of PrestaShop check
     * box values or select options.
     */
    protected function getPrestaShopOptions(array $options): array
    {
        $result = [
            'query' => [],
            'id' => 'id',
            'name' => 'name',
        ];
        foreach ($options as $value => $label) {
            $result['query'][] = [
                'id' => $value,
                'name' => $label,
            ];
        }
        return $result;
    }
}
