<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Form;
use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRendererSettings renders a settings form in the WordPress standard.
 */
class FormRendererSettings extends BaseFormRenderer {

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
   * This override echo's the output besides returning it as WordPress is in
   * outputting mode when this method gets called.
   */
  public function field(array $field) {
    $output = parent::field($field);
    echo $output;
    return $output;
  }

  /**
   * @inheritdoc
   *
   * This override gets called directly by the WordPress function
   * do_settings_section() which passes in an array with only the id and title
   * (and this callback). This override:
   * - Only renders the description as WordPress already renders the fieldset
   *   title and such.
   * - Instead of returning the output, it echo's the output.
   */
  public function renderFieldset($section) {
    $output = '';
    $field = $this->getFieldById($this->form->getFields(), $section['id']);
    if (!empty($field['description'])) {
      $output .= $this->renderDescription($field['description']);
    }
    echo $output;
    return $output;
  }

  /**
   * @inheritdoc
   *
   * This override:
   * - Skips the rendering of the wrapper and label as WordPress does that
   *   itself.
   * - Echo's the output as WordPress is in outputting mode here.
   */
  protected function renderField($field) {
    $type = $field['type'];
    $name = $field['name'];
    $value = isset($field['value']) ? $field['value'] : '';
    $attributes = $field['attributes'];
    $description = isset($field['description']) ? $field['description'] : '';
    $options = isset($field['options']) ? $field['options'] : array();

    $output = '';

    $output .= $this->renderElement($type, $name, $value, $attributes, $options);
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
