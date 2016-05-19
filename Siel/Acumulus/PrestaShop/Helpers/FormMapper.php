<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\Form;

/**
 * FormMapper maps an Acumulus form definition to a PrestaShop form definition.
 */
class FormMapper
{
    /**
     * Maps a set of field definitions.
     *
     * @param Form $form
     *
     * @return array[]
     */
    public function map(Form $form)
    {
        return $this->fields($form->getFields());
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     *
     * @return array[]
     */
    protected function fields(array $fields)
    {
        $result = array();
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
     *   Field(set) definition.
     *
     * @return array
     */
    protected function field(array $field)
    {
        if ($field['type'] === 'fieldset') {
            $result = $this->fieldset($field);
        } else {
            $result = $this->element($field);
        }
        return $result;
    }

    /**
     * Returns a mapped fieldset.
     *
     * @param array $field
     *
     * @return array[]
     */
    protected function fieldset(array $field)
    {
        $result = array(
            'form' => array(
                'legend' => array(
                    'title' => $field['legend'],
                ),
                'input' => $this->fields($field['fields']),
            ),
        );

        // Add description at the start of the fieldset as an html element.
        if (isset($field['description'])) {
            array_unshift($result['form']['input'], array('type' => 'html', 'html_content' => $field['description']));
        }
        // Add icon to legend.
        if (isset($field['icon'])) {
            $result['form']['legend']['icon'] = $field['icon'];
        }
        return $result;
    }

    /**
     * Returns a mapped simple element.
     *
     * @param array $field
     *
     * @return array
     *
     */
    protected function element(array $field)
    {
        $result = array(
            'type' => $this->getPrestaShopType($field['type']),
            'label' => isset($field['label']) ? $field['label'] : '',
            'name' => $field['name'],
            'required' => isset($field['attributes']['required']) ? $field['attributes']['required'] : false,
            'multiple' => isset($field['attributes']['multiple']) ? $field['attributes']['multiple'] : false,
        );

        if (isset($field['attributes'])) {
            $result['attributes'] = $field['attributes'];
        }
        if (isset($field['description'])) {
            $result['desc'] = $field['description'];
        }

        if ($field['type'] === 'radio') {
            $result['values'] = $this->getPrestaShopValues($field['name'], $field['options']);
        } else if ($field['type'] === 'checkbox') {
            $result['values'] = $this->getPrestaShopOptions($field['options']);
        } else if ($field['type'] === 'select') {
            $result['options'] = $this->getPrestaShopOptions($field['options']);
            if ($result['multiple']) {
                $result['size'] = $field['attributes']['size'];
            }
        }

        return $result;
    }

    /**
     * Returns the PrestaShop form element type for the given Acumulus type string.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getPrestaShopType($type)
    {
        switch ($type) {
            case 'fieldset':
            case 'markup':
                $type = 'free';
                break;
            case 'email':
                $type = 'text';
                break;
            default:
                // Return as is: text, password, textarea, radio, select, checkbox,
                // date. PrestaShop accepts these as are.
                break;
        }
        return $type;
    }

    /**
     * Converts a list of Acumulus field options to a list of PrestaShop radio
     * button values.
     *
     * @param string $id
     * @param array $options
     *
     * @return array A list of PrestaShop radio button options.
     * A list of PrestaShop radio button options.
     */
    protected function getPrestaShopValues($id, array $options)
    {
        $result = array();
        foreach ($options as $value => $label) {
            $result[] = array(
                'id' => $id . $value,
                'value' => $value,
                'label' => $label,
            );
        }
        return $result;
    }

    /**
     * Converts a list of Acumulus field options to a list of PrestaShop radio
     * button values.
     *
     * @param array $options
     *
     * @return array A list of PrestaShop radio button options.
     * A list of PrestaShop radio button options.
     */
    protected function getPrestaShopOptions(array $options)
    {
        $result = array(
            'query' => array(),
            'id' => 'id',
            'name' => 'name',
        );

        foreach ($options as $value => $label) {
            $result['query'][] = array(
                'id' => $value,
                'name' => $label,
            );
        }

        return $result;
    }
}
