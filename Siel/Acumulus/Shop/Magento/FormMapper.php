<?php
namespace Siel\Acumulus\Shop\Magento;

use Mage;
use Mage_Core_Model_Locale;
use Varien_Data_Form_Abstract;

/**
 * Class FormMapper maps an Acumulus form definition to a Magento form
 * definition.
 */
class FormMapper {

  /** @var bool */
  protected $hasRadios = FALSE;

  /**
   * Maps a set of field definitions.
   *
   * @param \Varien_Data_Form_Abstract $form
   * @param array[] $fields
   */
  public function map(Varien_Data_Form_Abstract $form, array $fields) {
    $this->fields($form, $fields);
    if ($this->hasRadios) {
      $form->addField('radio-styling',
        'note',
        array('text' => '<style> input[type=radio] { float: left; clear: both; margin-top: 0.2em;} .value label.inline {float: left !important; max-width: 95%; padding-left: 1em;} .note {clear: both;}</style>'),
        '^');
    }
  }

  /**
   * Maps a set of field definitions.
   *
   * @param \Varien_Data_Form_Abstract $parent
   * @param array[] $fields
   */
  public function fields($parent, array $fields) {
    foreach ($fields as $id => $field) {
      if (!isset($field['name'])) {
        $field['name'] = $id;
      }
      $this->field($parent, $field);
    }
  }

  /**
   * Maps a single field definition.
   *
   * @param \Varien_Data_Form_Abstract $parent
   * @param array $field
   */
  public function field($parent, array $field) {
    if (!isset($field['attributes'])) {
      $field['attributes'] = array();
    }
    $element = $parent->addField($field['name'], $this->getMagentoType($field['type']), $this->getMagentoElementSettings($field));
    if ($field['type'] === 'fieldset') {
      $this->fields($element, $field['fields']);
    }
  }

  /**
   * Returns the Magento form element type for the given Acumulus type string.
   *
   * @param string $type
   *
   * @return string
   */
  protected function getMagentoType($type) {
    switch ($type) {
      case 'email':
        $type = 'text';
        break;
      case 'markup':
        $type = 'note';
        break;
      case 'radio':
        $type = 'radios';
        $this->hasRadios = TRUE;
        break;
      case 'checkbox':
        $type = 'checkboxes';
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
  protected function getMagentoElementSettings(array $field) {
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
   *   cases, 1 Acumulus field setting may result in multiple Magento settings.
   */
  protected function getMagentoProperty($key, $value, $type) {
    switch ($key) {
      // Fields to ignore:
      case 'type':
        $result = array();
        if ($value === 'date') {
          $result['image'] = Mage::getDesign()->getSkinUrl('images/grid-cal.gif');
        }
        break;
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
          // Make it an array for PHP POST processing, in case there are
          // multiple checkboxes.
          $value .= '[]';
        }
        $result = array($key => $value);
        break;
      case 'description':
        $result = array('after_element_html' => '<p class="note">' . $value . '</p>');
        break;
      case 'value':
        if ($type === 'markup') {
          $result = array('text' => $value);
        }
        else { // $type === 'hidden'
          $result = array('value' => $value);
        }
        break;
      case 'attributes':
        // In magento you add pure html attributes at the same level as the
        // "field attributes" that are for Magento.
        $result = $value;
        if (!empty($value['required'])) {
          if (isset($result['class'])) {
            $result['class'] .= ' ';
          }
          else {
            $result['class'] = '';
          }
          if ($type === 'radio') {
            unset($result['required']);
            $result['class'] .= 'validate-one-required-by-name';
          }
          else {
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
  protected function getMagentoOptions(array $options) {
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
