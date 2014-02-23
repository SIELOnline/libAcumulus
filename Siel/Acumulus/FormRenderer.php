<?php

/**
 * @file
 * Contains \Siel\Acumulus\FormRenderer
 */

namespace Siel\Acumulus;

/**
 * Class FormRenderer
 */
class FormRenderer {

  public function simpleField($type, $name, $label, $value = '', $attributes = array(), $description = '') {
    $output = '';

    $labelAttributes = array();
    if (isset($attributes['required'])) {
      $labelAttributes['required'] = $attributes['required'];
      unset($attributes['required']);
    }
    $output .= '<div class="form-element">';
    $output .= $this->label($label, $name, $labelAttributes);
    $output .= $this->$type($name, $value, $attributes);
    $output .= $this->description($description);
    $output .= '</div>';

    return $output;
  }

  public function listField($type, $name, $label, $options, $value = null, $attributes = array(), $description = '') {
    $output = '';

    $labelAttributes = array();
    if (isset($attributes['required'])) {
      $labelAttributes['required'] = $attributes['required'];
      unset($attributes['required']);
    }
    $output .= '<div class="form-element">';
    $output .= $this->label($label, $type === 'select' ? $name : null, $labelAttributes);
    $output .= $this->$type($name, $options, $value, $attributes);
    $output .= $this->description($description);
    $output .= '</div>';

    return $output;
  }

  /**
   * Renders a descriptive help text.
   *
   * @param string $text
   * @param string $tag
   * @param string $class
   *
   * @return string
   *   The rendered description.
   */
  public function description($text, $tag = 'div', $class = 'description') {
    $output = '';

    // Help text.
    if (!empty($text)) {
      $output .= "<$tag class=\"$class\">" . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false) . "</$tag>";
    }

    return $output;
  }

  /**
   * Renders a label.
   *
   * @param string $text
   *   The label text.
   * @param string|null $id
   *   The value of the for attribute. If null, not a label tag but a span with
   *   a class="label" will be rendered.
   * @param array $attributes
   *   Any additional attributes to render for the label. The array is a keyed
   *   array, the keys being the attribute names, the values being the
   *   value of that attribute. If that value is an array it is rendered as a
   *   joined string of the values separated by a space (e.g. multiple classes).
   *
   * @return string
   *   The rendered label.
   */
  public function label($text, $id = null, $attributes = array()) {
    $output = '';

    // Label.
    $required = !empty($attributes['required']) ? '<span class="required">*</span>' : '';
    unset($attributes['required']);
    if ($id === null) {
      if (isset($attributes['class'])) {
        if (is_array($attributes['class'])) {
          $attributes['class'][] = 'label';
        }
        else {
          $attributes['class'] .= ' label';
        }
      }
      else {
        $attributes['class'] = 'label';
      }
      $output .= '<span' . $this->renderAttributes($attributes) . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false) . $required . '</span>';
    }
    else {
      $attributes = array_merge(array('for' => $id), $attributes);
      $output .= '<label' . $this->renderAttributes($attributes) . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false) . $required . '</label>';
    }

    return $output;
  }

  /**
   * Renders a text field (input tag with type="text").
   *
   * @param string $name
   *   The name attribute of the text field, required.
   * @param string $value
   *   The initial value of the text field.
   * @param array $attributes
   *   Any additional attributes to render for the text field, think of size or
   *   maxlength. The array is a keyed array, the keys being the attribute
   *   names, the values being the value of that attribute. If that value is an
   *   array it is rendered as a joined string of the values separated by a
   *   space (e.g. multiple classes).
   *
   * @return string
   *   The rendered text field.
   */
  public function text($name, $value = '', $attributes = array()) {
    $output = '';

    // Text field.
    $attributes = array_merge(array('type' => 'text', 'name' => $name, 'id' => $name, 'value' => $value), $attributes);
    $output .= '<input' . $this->renderAttributes($attributes) . '/>';

    return $output;
  }

  /**
   * Renders a password field (input tag with type="password").
   *
   * @param string $name
   *   The name attribute of the password field, required.
   * @param string $value
   *   The initial value of the field.
   * @param array $attributes
   *   Any additional attributes to render for this field. The array is a keyed
   *   array, the keys being the attribute names, the values being the value of
   *   that attribute. If that value is an array it is rendered as a joined
   *   string of the values separated by a space (e.g. multiple classes).
   *
   * @return string
   *   The rendered field.
   */
  public function password($name, $value = '', $attributes = array()) {
    $output = '';

    // Text field.
    $attributes = array_merge(array('type' => 'password', 'name' => $name, 'id' => $name, 'value' => $value), $attributes);
    $output .= '<input' . $this->renderAttributes($attributes) . '/>';

    return $output;
  }

  /**
   * Renders a select element.
   *
   * @param string $name
   *   The name attribute of the select, required.
   * @param array $options
   *   The list of options as a keyed array, the keys being the value attribute
   *   of the option tag, the values being the text within the option tag.
   * @param mixed|null $selected
   *   The selected value, null if no value has to be set to selected.
   * @param array $attributes
   *   Any additional attributes to render for the select tag, think of disabled.
   *   The array is a keyed array, the keys being the attribute names, the
   *   values being the value of that attribute. If that value is an array it is
   *   rendered as a joined string of the values separated by a space (e.g.
   *   multiple classes).
   *
   * @return string
   *   The rendered select element.
   */
  public function select($name, $options, $selected = null, $attributes = array()) {
    $output = '';

    // Select tag.
    $attributes = array_merge(array('name' => $name, 'id' => $name), $attributes);
    $output .= '<select' . $this->renderAttributes($attributes) . '>';

    // Options.
    foreach ($options as $value => $text) {
      $optionAttributes = array('value' => $value);
      // Do not match 0 as value with null as selected, but do match 0 and '0'.
      if ($selected !== null && $value == $selected) {
        $optionAttributes['selected'] = 'selected';
      }
      $output .= '<option' . $this->renderAttributes($optionAttributes) . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false) . '</option>';
    }

    // End tag.
    $output .= '</select>';

    return $output;
  }

  /**
   * Renders a list of radio buttons (input tag with type="radio") enclosed in a div.
   *
   * @param string $name
   *   The name attribute for all the radio buttons, required.
   * @param array $options
   *   The list of radio buttons as a keyed array, the keys being the value
   *   attribute of the radio button, the values being the label of the radio
   *   button.
   * @param mixed|null $selected
   *   The selected value, null if no value has to be set to selected.
   * @param array $attributes
   *   Any additional attributes to render on the div tag. The array is a keyed
   *   array, the keys being the attribute names, the values being the value of
   *   that attribute. If that value is an array it is rendered as a joined
   *   string of the values separated by a space (e.g. multiple classes).
   *
   * @return string
   *   The rendered radio buttons.
   */
  public function radio($name, $options, $selected = null, $attributes = array()) {
    $output = '';

    // Add a class.
    if (!isset($attributes['class'])) {
      $attributes['class'] = 'form-element-radios';
    }

    // Div tag.
    $output .= '<div' . $this->renderAttributes($attributes) . '>';

    // Radio buttons.
    foreach ($options as $value => $text) {
      $id = $name . '_' . $value;
      $radioAttributes = array('type' => 'radio', 'name' => $name, 'id' => $id, 'value' => $value);
      // Do not match 0 as value with null as selected, but do match 0 and '0'.
      if ($selected !== null && $value == $selected) {
        $radioAttributes['checked'] = 'checked';
      }
      $output .= '<input' . $this->renderAttributes($radioAttributes) . '>';
      $output .= $this->label($text, $id);
    }

    // End tag.
    $output .= '</div>';

    return $output;
  }

  /**
   * Renders a list of checkboxes (input tag with type="checkbox") enclosed in a div.
   *
   * @param string $name
   *   The name attribute for all the checkboxes, required. When rendering
   *   multiple checkboxes, use a name that ends with [] for easy PHP processing.
   * @param array $options
   *   The list of checkboxes as a keyed array, the keys being the value
   *   attribute of the checkbox, the values being the label of the checkbox.
   * @param array $selected
   *   The selected values.
   * @param array $attributes
   *   Any additional attributes to render on the div tag. The array is a keyed
   *   array, the keys being the attribute names, the values being the value of
   *   that attribute. If that value is an array it is rendered as a joined
   *   string of the values separated by a space (e.g. multiple classes).
   *
   * @return string
   *   The rendered checkboxes.
   */
  public function checkbox($name, $options, $selected = array(), $attributes = array()) {
    $output = '';

    // Add a class.
    if (!isset($attributes['class'])) {
      $attributes['class'] = 'form-element-checkboxes';
    }

    // Div tag.
    $output .= '<div' . $this->renderAttributes($attributes) . '>';

    // Checkboxes.
    foreach ($options as $value => $text) {
      $id = $name . '_' . $value;
      $checkboxAttributes = array('type' => 'checkbox', 'name' => $name, 'id' => $id, 'value' => $value);
      if (in_array($value, $selected)) {
        $checkboxAttributes['checked'] = 'checked';
      }
      $output .= '<input' . $this->renderAttributes($checkboxAttributes) . '>';
      $output .= $this->label($text, $id);
    }

    // End tag.
    $output .= '</div>';

    return $output;
  }

  /**
   * @param $attributes
   *
   * @return array
   *
   */
  protected function renderAttributes($attributes) {
    $attributeString = '';
    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        $value = join(' ', $value);
      }
      $attributeString .= ' ' . htmlspecialchars($key, ENT_NOQUOTES, 'UTF-8', FALSE) . '="' . htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false) . '"';
    }
    return $attributeString;
  }


}
