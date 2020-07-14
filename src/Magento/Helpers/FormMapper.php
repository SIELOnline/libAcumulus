<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormMapper as BaseFormMapper;

/**
 * Class FormMapper maps an Acumulus form definition to a Magento form
 * definition.
 */
class FormMapper extends BaseFormMapper
{

    /**
     * The date format as Magento uses it.
     *
     * @var string
     */
    const DateFormat = 'yyyy-MM-dd';

    /**
     * The slug-name of the settings page on which to show the section.
     *
     * @var \Varien_Data_Form_Abstract|\Magento\Framework\Data\Form\AbstractForm
     */
    protected $magentoForm;

    /**
     * @param \Magento\Framework\Data\Form\AbstractForm|\Varien_Data_Form_Abstract $magentoForm
     *
     * @return $this
     */
    public function setMagentoForm($magentoForm)
    {
        $this->magentoForm = $magentoForm;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Form $form)
    {
        $this->fields($this->magentoForm, $form->getFields());
    }

    /**
     * Maps a set of field definitions.
     *
     * @param \Varien_Data_Form_Abstract|\Magento\Framework\Data\Form\AbstractForm $parent
     * @param array[] $fields
     */
    public function fields($parent, array $fields)
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
     * @param \Varien_Data_Form_Abstract|\Magento\Framework\Data\Form\AbstractForm $parent
     * @param array $field
     */
    public function field($parent, array $field)
    {
        if (!isset($field['attributes'])) {
            $field['attributes'] = array();
        }
        $magentoType = $this->getMagentoType($field);
        $magentoElementSettings = $this->getMagentoElementSettings($field);
        // Constructor of multiselect (and perhaps others as well) overwrites
        // some the settings passed into the constructor, so we add our settings
        // after the element has been constructed...
        $element = $parent->addField($field['id'], $magentoType, [])->addData($magentoElementSettings);

        if ($magentoType === 'note') {
            // Attributes are ignored by an element of type note, we add them to
            // a wrapper element using beforeElementHtml and afterElementHtml.
            $htmlAttributes = ['class', 'title'];
            $label = $element->getLabelHtml();
            /** @noinspection PhpUndefinedMethodInspection */
            if (!empty($element->getLabelIsHtml())) {
                /** @noinspection PhpUndefinedMethodInspection */
                $label = preg_replace('|<span>.+</span>|U', '<span>' . $element->getLabel() . '</span>', $label);
            }
            /** @noinspection PhpUndefinedMethodInspection */
            $element->setBeforeElementHtml('<div ' . $element->serialize($htmlAttributes) . '>' . $label);
            /** @noinspection PhpUndefinedMethodInspection */
            $element->setAfterElementHtml('</div>');
            /** @noinspection PhpUndefinedMethodInspection */
            $element->setLabel(null);
        }

        if (!empty($field['fields'])) {
            // Add description at the start of the fieldset/summary as a Note
            // element.
            if (!empty($field['description'])) {
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
            case 'details':
                $type = 'fieldset';
                break;
            default:
                // Other types are returned as is: fieldset, text, password,
                // date, button.
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
        $config = [
            'class'=> [],
        ];
        $config = $this->addMagentoAdminClasses($config, $field);
        foreach ($field as $key => $value) {
            $config = $this->getMagentoProperty($config, $key, $value, $field['type']);
        }
        $config['class'] = implode(' ', $config['class']);
        if (empty($config['class'])) {
            unset($config['class']);
        }

        return $config;
    }

    /**
     * Converts an Acumulus settings to a Magento setting.
     *
     * @param array $config
     *   The Magento settings constructed so far. New settings should be added
     *   to this array and this array should be returned.
     * @param string $key
     *   The name of the setting to convert.
     * @param mixed $value
     *   The value for the setting to convert.
     * @param string $type
     *   The Acumulus field type.
     *
     * @return array
     *   The Magento settings.
     */
    protected function getMagentoProperty(array $config, $key, $value, $type)
    {
        switch ($key) {
            // Fields to ignore:
            case 'id':
            case 'fields':
                break;
            // Fields to return unchanged:
            case 'legend':
            case 'label':
            case 'format':
                $config[$key] = $value;
                break;
            case 'type':
                if ($value === 'fieldset') {
                    $config['collapsable'] = true;
                    $config['opened'] = true;
                } elseif ($value === 'details') {
                    $config['before_element_html'] = '<details>';
                    $config['after_element_html'] = '</details>';
                }
                break;
            case 'summary':
                $config['before_element_html'] .= '<summary>' . $value . '</summary>';
                break;
            case 'collapsable':
            case 'opened':
                // Do overwrite, as it has explicitly been set.
                $config['collapsable'] = $value;
                break;
            case 'name':
                if ($type === 'checkbox') {
                    // Make it an array for PHP POST processing, in case there
                    // are multiple checkboxes.
                    $value .= '[]';
                }
                $config[$key] = $value;
                break;
            case 'description':
                // Note that the description of a fieldset is handled elsewhere.
                if (!empty($value) && $type !== 'fieldset') {
                    $config['after_element_html'] = '<p class="note">' . $value . '</p>';
                }
                break;
            case 'value':
                if ($type === 'markup') {
                    $config['text'] = $value;
                } else { // $type === 'hidden'
                    $config['value'] = $value;
                }
                break;
            case 'attributes':
                // In magento you add pure html attributes at the same level as
                // the "field attributes" that are for Magento. Most attributes
                // are accepted and rendered by input accepting elements, but a
                // Note (our markup type) ignores all these, as are all label
                // attributes.
                foreach ($value as $attributeName => $attributeValue) {
                    switch ($attributeName) {
                        case 'required':
                            // Required for a set of radio buttons is handled
                            // differently.
                            if ($attributeValue) {
                                if ($type === 'radio') {
                                    $config['class'][] = 'validate-one-required-by-name';
                                } else {
                                    $config['required'] = true;
                                }
                            }
                            break;
                        case 'class':
                            // Merge classes.
                            $class = (array) $attributeValue;
                            $config['class'] = array_merge($config['class'], $class);
                            if (in_array('acumulus-ajax', $class)) {
                                if (empty($config['onclick'])) {
                                    $config['onclick'] = 'acumulusAjaxHandling(this)';
                                } else {
                                    $config['onclick'] .= ';acumulusAjaxHandling(this)';
                                }
                            }
                            break;
                        case 'label':
                            // Just add them to the element. For a note, they
                            // will then be put into a wrapping element. That
                            // should be enough to address them in css.
                            if (isset($attributeValue['html'])) {
                                $attributeValue['label_is_html'] = $attributeValue['html'];
                                unset($attributeValue['html']);
                            }
                            $config = $this->getMagentoProperty($config, 'attributes', $attributeValue, $type);
                            break;
                        default:
                            // Do not overwrite settings that are already set.
                            if (!isset($config[$attributeName])) {
                                $config[$attributeName] = $attributeValue;
                            }
                            break;
                    }
                }

                break;
            case 'options':
                $config['values'] = $this->getMagentoOptions($value);
                break;
            default:
                $this->log->warning(__METHOD__ . "Unknown key '$key'");
                $config[$key] = $value;
                break;
        }

        // Handle
        return $config;
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
        $config = array();
        foreach ($options as $value => $label) {
            $config[] = array(
                'value' => $value,
                'label' => $label,
            );
        }
        return $config;
    }

    /**
     * Adds any classes typical for the Magento admin section.
     *
     * These classes are typically used to:
     * - Add styling from the admin theme.
     * - Add behavior.
     *
     * @param array $config
     * @param array $field
     *
     * @return array
     */
    protected function addMagentoAdminClasses(array $config, array $field)
    {
        // Add a class action-secondary to buttons (action-primary buttons are
        // part of the toolbar outside the form).
        if ($field['type'] === 'button') {
            $config['class'][] = 'action-secondary';
        }
        return $config;
    }
}
