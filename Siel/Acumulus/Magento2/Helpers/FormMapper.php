<?php
namespace Siel\Acumulus\Magento2\Helpers;

use Magento\Framework\Data\Form\AbstractForm;

/**
 * Class FormMapper maps an Acumulus form definition to a Magento 2 form
 * definition.
 */
class FormMapper
{
    /**
     * Maps a set of field definitions.
     *
     * @param \Magento\Framework\Data\Form\AbstractForm $form
     * @param array[] $fields
     */
    public function map(AbstractForm $form, array $fields)
    {
        $this->fields($form, $fields);
    }

    /**
     * Maps a set of field definitions.
     *
     * @param \Magento\Framework\Data\Form\AbstractForm $parent
     * @param array[] $fields
     */
    public function fields(AbstractForm $parent, array $fields)
    {
        foreach ($fields as $id => $field) {
            if (!isset($field['id'])) {
                $field['id'] = $id;
            }
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            $this->field($parent, $field);
        }
    }

    /**
     * Maps a single field definition.
     *
     * @param \Magento\Framework\Data\Form\AbstractForm $parent
     * @param array $field
     */
    public function field(AbstractForm $parent, array $field)
    {
        if (!isset($field['attributes'])) {
            $field['attributes'] = array();
        }
        $element = $parent->addField($field['id'], $this->getMagentoType($field), $this->getMagentoElementSettings($field));

        if ($field['type'] === 'fieldset') {
            // Add description at the start of the fieldset as a note element.
            if (isset($field['description'])) {
                $element->addField($field['id'] . '-note', 'note', array('text' => '<p class="note">' . $field['description'] . '</p>'));
            }

            // Add fields of fieldset.
            $this->fields($element, $field['fields']);
        }
    }

    /**
     * Returns the Magento form element type for the given Acumulus type string.
     *
     * @param array $field
     *
     * @return string
     */
    protected function getMagentoType(array $field)
    {
        switch ($field['type']) {
            case 'email':
            case 'number':
                $type = 'text';
                break;
            case 'markup':
                $type = 'note';
                break;
            case 'radio':
                $type = 'radios';
                break;
            case 'checkbox':
                $type = 'checkboxes';
                break;
            case 'select':
                $type = empty($field['attributes']['multiple']) ? 'select' : 'multiselect';
                break;
            default:
                $type = $field['type'];
                break;
        }
        return $type;
    }

    /**
     * Returns the Magento form element settings.
     *
     * @param array $field
     *   The Acumulus field settings.
     *
     * @return array
     *   The Magento form element settings.
     */
    protected function getMagentoElementSettings(array $field)
    {
        $config = array();

        foreach ($field as $key => $value) {
            $config += $this->getMagentoProperty($key, $value, $field['type']);
        }

        return $config;
    }

    /**
     * Converts an Acumulus settings to a Magento setting.
     *
     * @param string $key
     *   The name of the setting to convert.
     * @param mixed $value
     *   The value for the setting to convert.
     * @param string $type
     *   The Acumulus field type.
     *
     * @return array
     *   The Magento setting. This will typically contain 1 element, but in some
     *   cases 1 Acumulus field setting results in multiple Magento settings.
     */
    protected function getMagentoProperty($key, $value, $type)
    {
        switch ($key) {
            // Fields to ignore:
            case 'type':
                $result = array();
                break;
            case 'id':
            case 'fields':
                $result = array();
                break;
            // Fields to return unchanged:
            case 'legend':
            case 'label':
            case 'format':
                $result = array($key => $value);
                break;
            case 'name':
                if ($type === 'checkbox') {
                    // Make it an array for PHP POST processing, in case there
                    // are multiple checkboxes.
                    $value .= '[]';
                }
                $result = array($key => $value);
                break;
            case 'description':
                // The description of a fieldset is handled elsewhere.
                $result = $type !== 'fieldset' ? array('after_element_html' => '<p class="note">' . $value . '</p>') : array();
                break;
            case 'value':
                if ($type === 'markup') {
                    $result = array('text' => $value);
                } else { // $type === 'hidden'
                    $result = array('value' => $value);
                }
                break;
            case 'attributes':
                // In magento you add pure html attributes at the same level as
                // the "field attributes" that are for Magento.
                $result = $value;
                if (!empty($value['required'])) {
                    if (isset($result['class'])) {
                        $result['class'] .= ' ';
                    } else {
                        $result['class'] = '';
                    }
                    if ($type === 'radio') {
                        unset($result['required']);
                        $result['class'] .= 'validate-one-required-by-name';
                    } else {
                        $result['class'] .= 'required-entry';
                    }
                }
                break;
            case 'options':
                $result = array('values' => $this->getMagentoOptions($value));
                break;
            default:
                Log::getInstance()->warning(__METHOD__ . "Unknown key '$key'");
                $result = array($key => $value);
                break;
        }
        return $result;
    }

    /**
     * Converts a list of Acumulus field options to a list of Magento options.
     *
     * @param array $options
     *
     * @return array
     *   A list of Magento form element options.
     */
    protected function getMagentoOptions(array $options)
    {
        $result = array();
        foreach ($options as $value => $label) {
            $result[] = array(
                'value' => $value,
                'label' => $label,
            );
        }
        return $result;
    }
}
