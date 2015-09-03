<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\Form;

/**
 * FormMapper maps an Acumulus form definition to a PrestaShop form definition.
 */
class FormMapper {

  /** @var array[] */
  protected $fields_form;

  /**
   * Maps a set of field definitions.
   *
   * @param Form $form
   *
   * @return array[]
   */
  public function map(Form $form) {
    $this->fields_form = array();
    $this->fields($form->getFields());
    return $this->fields_form;
  }

  /**
   * Maps a set of field definitions.
   *
   * @param array[] $fields
   */
  protected function fields(array $fields) {
    foreach ($fields as $id => $field) {
      if (!isset($field['name'])) {
        $field['name'] = $id;
      }
      $this->field($field);
    }
  }

  /**
   * Maps a single field definition.
   *
   * @param array $field
   *   Field(set) definition.
   */
  protected function field(array $field) {
    if ($field['type'] === 'fieldset') {
      // Fieldsets are not possible in PrestaShop. Add the "legend" as a free
      // field and subsequently add all fields at the same level.
      $this->fields_form[] = $this->element($field);
      $this->fields($field['fields']);
    }
    else {
      $this->fields_form[] = $this->element($field);
    }
  }

  /**
   *
   *
   * @param array $field
   *
   * @return array
   *
   */
  protected function element(array $field) {
    $result = array(
      'type' => $this->getPrestaShopType($field['type']),
      'label' => $field['label'],
      'name' => $field['name'],
      'required' => isset($field['attributes']['required']) ? $field['attributes']['required'] : false,
    );

    if (isset($field['attributes'])) {
      $result['attributes'] = $field['attributes'];
    }
    if (isset($field['description'])) {
      $result['desc'] = $field['description'];
    }
    if ($field['type'] === 'fieldset') {
      $result['label'] = '<h2>' . $result['label'] . '</h2>';
    }
    else if ($field['type'] === 'radio') {
      $result['values'] = $this->getPrestaShopValues($field['id'], $field['options']);
    }
    else if ($field['type'] === 'checkbox') {
      $result['values'] = $this->getPrestaShopOptions($field['options']);
    }
    else if ($field['type'] === 'select') {
      $result['options'] = $this->getPrestaShopOptions($field['options']);
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
  protected function getPrestaShopType($type) {
    switch ($type) {
      case 'fieldset':
      case 'markup':
        $type = 'free';
        break;
      default:
        // Return as is: text, password, textarea, radio, select, checkbox,
        // email, date. PrestaShop accepts all these (HTML5) types.
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
  protected function getPrestaShopValues($id, array $options) {
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
  protected function getPrestaShopOptions(array $options) {
    $result = array();

    $result['query'] = array();
    foreach ($options as $value => $label) {
      $result['query'][] = array(
        'id' => $value,
        'name' => $label,
      );
    }
    $result[] = array(
      'id' => 'id',
      'name' => 'name'
    );

    return $result;
  }

}
