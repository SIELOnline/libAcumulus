<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Form;
use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the WordPress/WooCommerce standards.
 */
class FormRenderer extends BaseFormRenderer {

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
    settings_fields('woocommerce_acumulus');
    do_settings_sections('woocommerce_acumulus');
    submit_button();
    echo '</form>';
    echo '</div>';
    return ob_get_clean();
  }

  /**
   * @inheritdoc
   *
   * This override skips the rendering of the wrapper and label as WordPress
   * does that itself.
   */
  protected function renderField($field) {
    $type = $field['type'];
    $name = $field['name'];
    $value = isset($field['value']) ? $field['value'] : '';
    $attributes = isset($field['attributes']) ? $field['attributes'] : array();
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
