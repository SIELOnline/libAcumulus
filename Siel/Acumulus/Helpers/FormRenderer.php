<?php
namespace Siel\Acumulus\Helpers;

/**
 * Provides form element rendering functionality.
 */
class FormRenderer {

  /** @var \Siel\Acumulus\Helpers\Form */
  protected $form;

  /** @var bool */
  protected $html5 = true;

  /** @var string */
  protected $elementWrapperTag = 'div';

  /** @var string */
  protected $elementWrapperClass = 'form-element';

  /** @var string */
  protected $fieldsetWrapperTag = 'fieldset';

  /** @var string */
  protected $fieldsetWrapperClass = '';

  /** @var string */
  protected $legendWrapperTag = 'legend';

  /** @var string */
  protected $legendWrapperClass = '';

  /** @var string */
  protected $labelWrapperTag = '';

  /** @var string */
  protected $labelWrapperClass = '';

  /** @var string */
  protected $inputWrapperTag = '';

  /** @var string */
  protected $inputWrapperClass = '';

  /** @var string */
  protected $radioWrapperTag = 'div';

  /** @var string */
  protected $radioWrapperClass = 'radio';

  /** @var string */
  protected $checkboxWrapperTag = 'div';

  /** @var string */
  protected $checkboxWrapperClass = 'checkbox';

  /** @var string */
  protected $multiLabelTag = 'span';

  /** @var string */
  protected $multiLabelClass = 'label';

  /** @var string */
  protected $descriptionClass = 'description';

  /** @var string */
  protected $requiredMarkup = '<span class="required">*</span>';

  /**
   * FormRenderer constructor.
   *
   * @param \Siel\Acumulus\Helpers\Form $form
   */
  public function __construct(Form $form) {
    $this->form = $form;
  }

  public function fields(array $fields) {
    $output = '';
    foreach ($fields as $name => $field) {
      if (!isset($field['name'])) {
        $field['name'] = $name;
      }
      $output .= $this->field($field);
    }
    return $output;
  }

  public function field(array $field) {
    $output = '';
    if (!isset($field['attributes'])) {
      $field['attributes'] = array();
    }
    switch ($field['type']) {
      case 'fieldset':
        $output .= $this->fieldset($field);
        $output .= $this->fields($field['fields']);
        $output .= $this->fieldsetEnd($field);
        break;
      case 'markup':
        $output .= $field['value'];
        break;
      default:
        $output .= $this->element($field);
        break;
    }
    return $output;
  }

  protected function fieldset($field) {
    $output = '';
    $output .= $this->getWrapper('fieldset', $field['attributes']);
    $output .= $this->getWrapper('legend', $field['attributes']);
    $output .= $field['legend'];
    $output .= $this->getWrapperEnd('legend');
    if (!empty($field['description'])) {
      $output .= $this->description($field['description']);
    }
    return $output;
  }

  protected function fieldsetEnd($field) {
    $output = '';
    $output .= $this->getWrapperEnd('fieldset');
    return $output;
  }

  /**
   * Renders a form element based on a field definition.
   *
   * @param array $field
   *
   * @return string
   *   html for this form element.
   */
  protected function element(array $field) {
    $type = $field['type'];
    $name = $field['name'];
    $label = isset($field['label']) ? $field['label'] : '';
    $value = isset($field['value']) ? $field['value'] : '';
    $attributes = isset($field['attributes']) ? $field['attributes'] : array();
    $description = isset($field['description']) ? $field['description'] : '';
    $options = isset($field['options']) ? $field['options'] : null;

    return $this->renderLabelAndField($type, $name, $label, $value, $attributes, $description, $options);
  }

  /**
   * Renders a form element including its label
   *
   * @param string $type
   * @param string $name
   * @param string $label
   * @param string $value
   * @param array $attributes
   * @param string $description
   * @param array|null $options
   *
   * @return string
   *   html for this form element.
   *
   * @deprecated: to be merged with method element()?
   */
  protected function renderLabelAndField($type, $name, $label, $value, array $attributes, $description, $options = null) {
    $output = '';

    $labelAttributes = array();
    if (!empty($attributes['required'])) {
      $labelAttributes['required'] = $attributes['required'];
    }
    $output .= $this->getWrapper('element');
    $output .= $this->label($label, $type !== 'radio' && $type !== 'checkbox' ? $name : null, $labelAttributes);
    switch ($type) {
      case 'textarea':
        $output .= $this->textarea($name, $value, $attributes);
        break;
      case 'select':
      case 'radio':
      case 'checkbox':
        $output .= $this->$type($name, $options, $value, $attributes);
        break;
      case 'hidden':
        // Overrule output rendering: only output the hidden field itself, no
        // other markup around it.
        return $this->input($type, $name, $value, $attributes);
      default:
        $output .= $this->input($type, $name, $value, $attributes);
        break;
    }
    $output .= $this->description($description);
    $output .= $this->getWrapperEnd('element');

    return $output;
  }

  /**
   * @deprecated
   */
  public function simpleField($type, $name, $label, $value = '', $attributes = array(), $description = '') {
    return $this->renderLabelAndField($type, $name, $label, $value, $attributes, $description);
  }

  /**
   * @deprecated
   */
  public function listField($type, $name, $label, $options, $value = null, $attributes = array(), $description = '') {
    return $this->renderLabelAndField($type, $name, $label, $value, $attributes, $description, $options);
  }

  /**
   * Renders a descriptive help text.
   *
   * @param string $text
   * @param string $tag
   *
   * @return string
   *   The rendered description.
   */
  public function description($text, $tag = 'div') {
    $output = '';

    // Help text.
    if (!empty($text)) {
      $output .= "<$tag class=\"{$this->descriptionClass}\">" . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . "</$tag>";
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
   * @param bool $isMainLabel
   *
   * @return string The rendered label.
   * The rendered label.
   */
  public function label($text, $id = null, array $attributes = array(), $isMainLabel = true) {
    $output = '';

    $attributes = $this->addLabelAttributes($attributes, $id);

    // Tag around main labels.
    if ($isMainLabel) {
      $output .= $this->getWrapper('label', $attributes);
    }

    // Label.
    $required = !empty($attributes['required']) ? $this->requiredMarkup : '';
    $tag = empty($id) ? $this->multiLabelTag : 'label';
    $output .= '<' . $tag . $this->renderAttributes($attributes) . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . $required . '</' . $tag . '>';

    // Tag around labels.
    if ($isMainLabel) {
      $output .= $this->getWrapperEnd('label');
    }
    return $output;
  }

  /**
   * Renders an input field.
   *
   * @param string $type
   *   The input type, required.
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
  public function input($type, $name, $value = '', array $attributes = array()) {
    $output = '';

    // Tag around input element.
    $output .= $this->getWrapper('input');

    $attributes = $this->addAttribute($attributes, 'type', $type);
    $attributes = $this->addAttribute($attributes, 'id', $name);
    $attributes = $this->addAttribute($attributes, 'name', $name);
    $attributes = $this->addAttribute($attributes, 'value', $value);
    $output .= '<input' . $this->renderAttributes($attributes) . '/>';

    // Tag around input element.
    $output .= $this->getWrapperEnd('input');

    return $output;
  }

  /**
   * Renders a textarea field.
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
   *   The rendered textarea field.
   */
  public function textarea($name, $value = '', array $attributes = array()) {
    $output = '';

    // Tag around input element.
    $output .= $this->getWrapper('input');

    $attributes = $this->addAttribute($attributes, 'id', $name);
    $attributes = $this->addAttribute($attributes, 'name', $name);
    $output .= '<textarea' . $this->renderAttributes($attributes) . '>';
    $output .=  htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
    $output .= '</textarea>';

    // Tag around input element.
    $output .= $this->getWrapperEnd('input');

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
   *
   * @deprecated
   */
  public function text($name, $value = '', array $attributes = array()) {
    return $this->input('text', $name, $value, $attributes);
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
   *
   * @deprecated
   */
  public function password($name, $value = '', array $attributes = array()) {
    return $this->input('password', $name, $value, $attributes);
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
  public function select($name, $options, $selected = null, array $attributes = array()) {
    $output = '';

    // Tag around select element: same as for an input element.
    $output .= $this->getWrapper('input');

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
      $output .= '<option' . $this->renderAttributes($optionAttributes) . '>' . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . '</option>';
    }

    // End tag.
    $output .= '</select>';
    // Tag around select element.
    $output .= $this->getWrapperEnd('input');

    return $output;
  }

  /**
   * Renders a list of radio buttons (input tag with type="radio").
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
  public function radio($name, $options, $selected = null, array $attributes = array()) {
    $output = '';

    // Handling of required attribute: may appear on on all radio buttons with
    // the same name.
    $required = !empty($attributes['required']);
    unset($attributes['required']);

    // Tag(s) around radio buttons.
    $output .= $this->getWrapper('input', $attributes);
    $output .= $this->getWrapper('radio', $attributes);

    // Radio buttons.
    foreach ($options as $value => $text) {
      $radioAttributes = $this->getRadioAttributes($name, $value);
      $radioAttributes = $this->addAttribute($radioAttributes, 'required', $required);
      // Do not match 0 as value with null as selected, but do match 0 and '0'.
      if ($selected !== null && $value == $selected) {
        $radioAttributes['checked'] = true;
      }
      $output .= '<input' . $this->renderAttributes($radioAttributes) . '>';
      $output .= $this->label($text, $radioAttributes['id'], array(), false);
    }

    // End tag.
    $output .= $this->getWrapperEnd('radio');
    $output .= $this->getWrapperEnd('input');

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
  public function checkbox($name, $options, $selected = array(), array $attributes = array()) {
    $output = '';

    // Div tag.
    unset($attributes['required']);
    $output .= $this->getWrapper('input', $attributes);
    $output .= $this->getWrapper('checkbox', $attributes);

    // Checkboxes.
    foreach ($options as $value => $text) {
      $checkboxAttributes = $this->getCheckboxAttributes($name, $value);
      if (in_array($value, $selected)) {
        $checkboxAttributes['checked'] = true;
      }
      $output .= '<input' . $this->renderAttributes($checkboxAttributes) . '>';
      $output .= $this->label($text, $checkboxAttributes['id'], array(), false);
    }

    // End tag.
    $output .= $this->getWrapperEnd('checkbox');
    $output .= $this->getWrapperEnd('input');

    return $output;
  }

  /**
   * @param string $type
   * @param array $attributes
   *
   * @return string
   */
  protected function getWrapper($type, array $attributes = array()) {
    $tag = "{$type}WrapperTag";
    $class = "{$type}WrapperClass";
    $output = '';
    if (!empty($this->$tag)) {
      if (!empty($this->$class)) {
        $attributes = $this->addAttribute($attributes, 'class', $this->$class);
      }
      $output .= "<{$this->$tag}";
      $output .= $this->renderAttributes($attributes);
      $output .= '>';
    }
    return $output;
  }

  /**
   * @param string $type
   *
   * @return string
   */
  protected function getWrapperEnd($type) {
    $tag = "{$type}WrapperTag";
    $output = '';
    if (!empty($this->$tag)) {
      $output .= "</{$this->$tag}>";
    }
    return $output;
  }

  /**
   * Renders a list of attributes.
   *
   * @param array $attributes
   *
   * @return string
   *   html string with the rendered attributes and 1 space in front of it.
   */
  protected function renderAttributes(array $attributes) {
    $attributeString = '';
    foreach ($attributes as $key => $value) {
      if (is_array($value)) {
        $value = implode(' ', $value);
      }
      // Skip attributes that are not to be set (required, disabled, ...).
      if ($value !== false ) {
        $attributeString .= ' ' . htmlspecialchars($key, ENT_NOQUOTES, 'UTF-8');
        // HTML5: do not add a value to boolean attributes.
        // HTML4: add the name of the key as value for the attribute.
        if (!$this->html5 && $value === true) {
          $value = $key;
        }
        if ($value !== true) {
          $attributeString .= '="' . htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8') . '"';
        }
      }
    }
    return $attributeString;
  }

  /**
   * @param array $attributes
   *   The array of attributes to add the value to.
   * @param string $attribute
   *   The name of the attribute to add.
   * @param string $value
   *   The value of the attribute to add.
   * @param bool|null $multiple
   *   Allow multiple values for the given attribute. By default this is
   *   only allowed for the class attribute.
   *
   * @return array
   *   The set of attributes with the value added.
   */
  protected function addAttribute(array $attributes, $attribute, $value, $multiple = null) {
    // Do add false and 0, but not an empty string or null.
    if ($value !== null && $value !== '') {
      if ($multiple === null) {
        $multiple = $attribute === 'class';
      }
      if ($multiple) {
        // Multiple values allowed: set or add, not overwriting.
        if (isset($attributes[$attribute])) {
          // Assure it is an array, not a scalar
          $attributes[$attribute] = (array) $attributes[$attribute];
        }
        else {
          // Set as an empty array
          $attributes[$attribute] = array();
        }
        // Now we know for sure that it is an array, add it.
        $attributes[$attribute][] = $value;
      }
      else {
        // Single value: just set, possibly overwriting.
        $attributes[$attribute] = $value;
      }
    }
    return $attributes;
  }

  /**
   * @param array $attributes
   * @param string $id
   *
   * @return array
   */
  protected function addLabelAttributes(array $attributes, $id) {
    $attributes = $this->addAttribute($attributes, 'for', $id);
    if (empty($id)) {
      $attributes = $this->addAttribute($attributes, 'class', $this->multiLabelClass);
    }
    return $attributes;
  }

  /**
   * @param string $name
   * @param string $value
   *
   * @return array
   */
  protected function getCheckboxAttributes($name, $value) {
    $checkboxAttributes = array(
      'type' => 'checkbox',
      'name' => $value,
      'id' => "{$name}_{$value}",
      'value' => 1,
    );
    return $checkboxAttributes;
  }

  /**
   * @param string $name
   * @param string $value
   *
   * @return array
   */
  protected function getRadioAttributes($name, $value) {
    $radioAttributes = array(
      'type' => 'radio',
      'name' => $name,
      'id' => "{$name}_{$value}",
      'value' => $value);
    return $radioAttributes;
  }

}
