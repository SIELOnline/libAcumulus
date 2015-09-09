<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Form;
use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the WordPress/WooCommerce standards.
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
   * This override uses the WordPress form related functions to output the form.
   */
  public function render(Form $form) {
    ob_start();
    echo '<div class="wrap">';
    /** @noinspection HtmlUnknownTarget */
    echo '<form method="post" action="options.php">';
    settings_fields('acumulus');
    do_settings_sections('acumulus');
    submit_button();
    echo '</form>';
    echo '</div>';
    return ob_get_clean();
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

}
