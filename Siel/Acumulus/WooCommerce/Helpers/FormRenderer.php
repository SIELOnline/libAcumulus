<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Form;
use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the WordPress settings pages standard.
 */
class FormRenderer extends BaseFormRenderer {

  protected $radioWrapperTag = 'ul';
  protected $radioWrapperClass = '';

  protected $radio1WrapperTag = 'li';
  protected $radio1WrapperClass = '';

  protected $checkboxWrapperTag = 'ul';
  protected $checkboxWrapperClass = '';

  protected $checkbox1WrapperTag = 'li';
  protected $checkbox1WrapperClass = '';

  /**
   * @inheritdoc
   *
   * This override does only set the form, as we need it later on to retrieve
   * additional information about field sets.
   */
  public function render(Form $form) {
    $this->form = $form;
    return '';
  }

  /**
   * @inheritdoc
   *
   * This override makes access public and echo's the output besides returning
   * it as WordPress is in field by field outputting mode when this method gets
   * called.
   */
  public function field(array $field) {
    $output = parent::field($field);
    echo $output;
    return $output;
  }

  /**
   * @inheritdoc
   *
   * This override only renders the description as WordPress already renders the
   * fieldset title and the fields.
   */
  protected function renderFieldset(array $field) {
    $output = '';
    if (!empty($field['description'])) {
      $output .= $this->renderDescription($field['description']);
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   *
   * This override:
   * - Skips the rendering of the wrapper and label as WordPress does that
   *   itself.
   * - Echo's the output as WordPress is in outputting mode here.
   */
  protected function renderField(array $field) {
    $type = $field['type'];
    $id = $field['id'];
    $name = $field['name'];
    $value = isset($field['value']) ? $field['value'] : '';
    $attributes = $field['attributes'];
    $description = isset($field['description']) ? $field['description'] : '';
    $options = isset($field['options']) ? $field['options'] : array();

    $output = '';

    $output .= $this->renderElement($type, $id, $name, $value, $attributes, $options);
    if ($type !== 'hidden') {
      $output .= $this->renderDescription($description);
    }
    return $output;
  }

  /**
   * Recursively searches a field by its id.
   *
   * @param array[] $fields
   * @param string $id
   *
   * @return array|null
   */
  protected function getFieldById($fields, $id) {
    if (array_key_exists($id, $fields)) {
      return $fields[$id];
    }
    foreach ($fields as $id => $field) {
      if (isset($field['fields'])) {
        $result = $this->getFieldById($field['fields'], $id);
        if ($result !== NULL) {
          return $result;
        }
      }
    }
    return NULL;
  }

}
