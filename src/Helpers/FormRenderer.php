<?php
namespace Siel\Acumulus\Helpers;

/**
 * Provides form element rendering functionality. This basic implementation
 * renders the elements as wrapped html input elements. To comply with shop
 * specific styling, it is supposed to be overridden per shop that uses this
 * way of rendering. For now those are: HikaShop/VirtueMart (Joomla), OpenCart,
 * and WooCommerce (WordPress).
 *
 * SECURITY REMARKS
 * ----------------
 * - All values (inputs) and texts (between opening and closing tag) are passed
 *   through htmlspecialchars().
 * - The exceptions being:
 *   * A label prefix and postfix that come from code and may contain html.
 *     @see FormRenderer::renderLabel().
 *   * markup is rendered as is as it may containhtml, therefore its name ...
 *     @see FormRenderer::markup();
 * - All tags come from object properties or are hard coded and thus present no
 *   security risk but they are passed through htmlspecialchars() anyway. @see
 *   FormRenderer::getOpenTag() and FormRenderer::getCloseTag().
 * - All attributes, name and value are passed through htmlpecialchars(). @see
 *   FormRenderer::renderAttributes().
 */
class FormRenderer
{
    const RequiredMarkup = '<span class="required">*</span>';

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
    protected $fieldsetDescriptionWrapperTag = 'div';

    /** @var string */
    protected $fieldsetDescriptionWrapperClass = 'fieldset-description';

    /** @var string */
    protected $fieldsetContentWrapperTag = '';

    /** @var string */
    protected $fieldsetContentWrapperClass = 'fieldset-content';

    /** @var string */
    protected $labelWrapperTag = '';

    /** @var string */
    protected $labelWrapperClass = '';

    /** @var string */
    protected $markupWrapperTag = 'div';

    /** @var string */
    protected $markupWrapperClass = 'message';

    /** @var string */
    protected $inputDescriptionWrapperTag = '';

    /** @var string */
    protected $inputDescriptionWrapperClass = '';

    /** @var string */
    protected $inputWrapperTag = '';

    /** @var string */
    protected $inputWrapperClass = '';

    /** @var string */
    protected $radioWrapperTag = 'div';

    /** @var string */
    protected $radioWrapperClass = 'radio';

    /** @var string */
    protected $radio1WrapperTag = '';

    /** @var string */
    protected $radio1WrapperClass = '';

    /** @var string */
    protected $checkboxWrapperTag = 'div';

    /** @var string */
    protected $checkboxWrapperClass = 'checkbox';

    /** @var string */
    protected $checkbox1WrapperTag = '';

    /** @var string */
    protected $checkbox1WrapperClass = '';

    /** @var string */
    protected $multiLabelTag = 'label';

    /** @var string */
    protected $multiLabelClass = '';

    /** @var string */
    protected $descriptionWrapperTag = 'div';

    /** @var string */
    protected $descriptionWrapperClass = 'description';

    /** @var bool */
    protected $radioInputInLabel = false;

    /** @var bool */
    protected $checkboxInputInLabel = false;

    /** @var string */
    protected $requiredMarkup = self::RequiredMarkup;

    /** @var bool */
    protected $usePopupDescription = false;

    /** @var Form */
    protected $form;

    /**
     * Sets the value of a property of this object.
     *
     * The property must exist as property
     *
     * @param string $property
     * @param mixed $value
     *
     * @return $this
     */
    public function setProperty($property, $value)
    {
        if (property_exists($this, $property) && !in_Array($property, array('form'))) {
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * Renders the form.
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *
     * @return string
     */
    public function render(Form $form)
    {
        $this->form = $form;
        $this->form->addValues();
        return $this->fields($this->form->getFields());
    }

    /**
     * Renders a set of field definitions.
     *
     * @param array[] $fields
     *
     * @return string
     */
    public function fields(array $fields)
    {
        $output = '';
        foreach ($fields as $id => $field) {
            if (!isset($field['id'])) {
                $field['id'] = $id;
            }
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            $output .= $this->field($field);
        }
        return $output;
    }

    /**
     * Renders 1 field definition (which may be a fieldset with multiple fields).
     *
     * @param array $field
     *   Array with the form field definition. the keys id and name are expected to be set.
     *
     * @return string
     */
    public function field(array $field)
    {
        $output = '';
        if (!isset($field['attributes'])) {
            $field['attributes'] = array();
        }
        $output .= $field['type'] === 'fieldset' ? $this->renderFieldset($field) : $this->renderField($field);
        return $output;
    }

    /**
     * Renders a fieldset.
     *
     * @param array $field
     *
     * @return string
     *   The rendered fieldset.
     */
    protected function renderFieldset(array $field)
    {
        $output = '';
        $output .= $this->fieldsetBegin($field);
        $output .= $this->fields($field['fields']);
        $output .= $this->fieldsetEnd($field);
        return $output;
    }

    /**
     * Outputs the beginning of a fieldset.
     *
     * @param array $field
     *
     * @return string
     */
    protected function fieldsetBegin(array $field)
    {
        $output = '';
        $output .= $this->getWrapper('fieldset', $field['attributes']);
        if (!empty($field['legend'])) {
            $output .= $this->getWrapper('legend', $field['attributes']);
            $output .= $field['legend'];
            $output .= $this->getWrapperEnd('legend');
        }
        $output .= $this->getWrapper('fieldsetContent');
        if (!empty($field['description'])) {
            $output .= $this->renderDescription($field['description'], true);
        }
        return $output;
    }

    /**
     * Outputs the end of a fieldset.
     *
     * @param array $field
     *
     * @return string
     */
    protected function fieldsetEnd(/** @noinspection PhpUnusedParameterInspection */ array $field)
    {
        $output = '';
        $output .= $this->getWrapperEnd('fieldsetContent');
        $output .= $this->getWrapperEnd('fieldset');
        return $output;
    }

    /**
     * Renders a form field including its label and description.
     *
     * @param array $field
     *
     * @return string
     *   Html for this form field.
     */
    protected function renderField(array $field)
    {
        $type = $field['type'];
        // Id and name may be empty/not set for markup fields.
        $id = isset($field['id']) ? $field['id'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $label = isset($field['label']) ? $field['label'] : '';
        $value = isset($field['value']) ? $field['value'] : '';
        $attributes = isset($field['attributes']) ? $field['attributes'] : array();
        $description = isset($field['description']) ? $field['description'] : '';
        $options = isset($field['options']) ? $field['options'] : array();

        $output = '';

        // Split attributes over label and element.
        $labelAttributes = array();
        if (!empty($attributes['label'])) {
            $labelAttributes = $attributes['label'];
            unset($attributes['label']);
        }
        if (!empty($attributes['required'])) {
            $labelAttributes['required'] = $attributes['required'];
        }

        if ($type !== 'hidden') {
            $output .= $this->getWrapper('element');
            $output .= $this->renderLabel($label, in_array($type, array('radio', 'checkbox', 'markup')) ? null : $id, $labelAttributes);
            $output .= $this->getWrapper('inputDescription');
        }
        $output .= $this->renderElement($type, $id, $name, $value, $attributes, $options);
        if ($type !== 'hidden') {
            $output .= $this->renderDescription($description);
            $output .= $this->getWrapperEnd('inputDescription');
            $output .= $this->getWrapperEnd('element');
        }
        return $output;
    }

    /**
     * Renders a form field itself, ie without label and description.
     *
     * @param string $type
     * @param string $id
     * @param string $name
     * @param string|int $value
     * @param array $attributes
     * @param array $options
     *
     * @return string
     */
    protected function renderElement($type, $id, $name, $value, array $attributes = array(), array $options = array())
    {
        switch ($type) {
            case 'textarea':
                return $this->textarea($id, $name, $value, $attributes);
            case 'select':
            case 'radio':
            case 'checkbox':
                return $this->$type($id, $name, $value, $options, $attributes);
            case 'markup':
                return $this->markup($id, $name, $value, $attributes);
            default:
                return $this->input($type, $id, $name, $value, $attributes);
        }
    }

    /**
     * Renders a descriptive help text.
     *
     * @param string $text
     * @param bool $isFieldset
     *
     * @return string
     *   The rendered description.
     */
    protected function renderDescription($text, $isFieldset = false)
    {
        $output = '';

        // Help text.
        if (!empty($text)) {
            // Allow for links in the help text, so no filtering anymore.
            $wrapperType = $isFieldset ? 'fieldsetDescription' : 'description';
            $output .= $this->getWrapper($wrapperType);
            $output .= $text;
            $output .= $this->getWrapperEnd($wrapperType);
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
     * @param bool $wrapLabel
     *   Whether to wrap this label within the defined label wrapper tag.
     * @param string $prefix
     *   Prefix to prepend to the label text, may contain html, so don't escape.
     *   Will come from code not users.
     * @param string $postfix
     *   Postfix to append to the label text, may contain html, so don't escape.
     *   Will come from code not users.
     *
     * @return string The rendered label.
     *   The rendered label.
     */
    protected function renderLabel($text, $id = null, array $attributes = array(), $wrapLabel = true, $prefix = '', $postfix = '')
    {
        $output = '';

        // Split attributes over label and wrapper.
        $wrapperAttributes = array();
        if (!empty($attributes['wrapper'])) {
            $wrapperAttributes = $attributes['wrapper'];
            unset($attributes['wrapper']);
        }
        if (!empty($attributes['required'])) {
            $wrapperAttributes['required'] = $attributes['required'];
        }

        // Tag around main labels.
        if ($wrapLabel) {
            $output .= $this->getWrapper('label', $wrapperAttributes);
        }

        // Label.
        $attributes = $this->addLabelAttributes($attributes, $id);
        $postfix .= !empty($attributes['required']) ? $this->requiredMarkup : '';
        $tag = empty($id) ? $this->multiLabelTag : 'label';
        $output .= $this->getOpenTag($tag, $attributes);
        $output .= $prefix . htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') . $postfix;
        $output .= $this->getCloseTag($tag);

        // Tag around labels.
        if ($wrapLabel) {
            $output .= $this->getWrapperEnd('label');
        }
        return $output;
    }

    /**
     * Renders an input field.
     *
     * @param string $type
     *   The input type, required.
     * @param string $id
     *   The id attribute of the text field, required.
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
    protected function input($type, $id, $name, $value = '', array $attributes = array())
    {
        $output = '';

        // Tag around input element.
        $output .= $this->getWrapper('input');

        $attributes = $this->addAttribute($attributes, 'type', $type);
        $attributes = $this->addAttribute($attributes, 'id', $id);
        $attributes = $this->addAttribute($attributes, 'name', $name);
        $attributes = $this->addAttribute($attributes, 'value', $value);
        $output .= $this->getOpenTag('input', $attributes, true);

        // Tag around input element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a textarea field.
     *
     * @param string $id
     *   The id attribute of the text field, required.
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
     * @return string The rendered textarea field.
     * The rendered textarea field.
     */
    protected function textarea($id, $name, $value = '', array $attributes = array())
    {
        $output = '';

        // Tag around input element.
        $output .= $this->getWrapper('input');

        $attributes = $this->addAttribute($attributes, 'id', $id);
        $attributes = $this->addAttribute($attributes, 'name', $name);
        $output .= $this->getOpenTag('textarea', $attributes);
        $output .= htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        $output .= $this->getCloseTag('textarea');

        // Tag around input element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a text field (input tag with type="text").
     *
     * @param string $id
     *   The id attribute of the text field, required.
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
    protected function text($id, $name, $value = '', array $attributes = array())
    {
        return $this->input('text', $id, $name, $value, $attributes);
    }

    /**
     * Renders a password field (input tag with type="password").
     *
     * @param string $id
     *   The id attribute of the password field, required.
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
    protected function password($id, $name, $value = '', array $attributes = array())
    {
        return $this->input('password', $id, $name, $value, $attributes);
    }

    /**
     * Renders a select element.
     *
     * @param string $id
     *   The id attribute of the select, required.
     * @param string $name
     *   The name attribute of the select, required.
     * @param mixed|null $selected
     *   The selected value, null if no value has to be set to selected.
     * @param array $options
     *   The list of options as a keyed array, the keys being the value attribute
     *   of the option tag, the values being the text within the option tag.
     * @param array $attributes
     *   Any additional attributes to render for the select tag, think of disabled.
     *   The array is a keyed array, the keys being the attribute names, the
     *   values being the value of that attribute. If that value is an array it is
     *   rendered as a joined string of the values separated by a space (e.g.
     *   multiple classes).
     *
     * @return string The rendered select element.
     * The rendered select element.
     */
    protected function select($id, $name, $selected, array $options, array $attributes = array())
    {
        $output = '';

        // Tag around select element: same as for an input element.
        $output .= $this->getWrapper('input');

        // Select tag.
        $attributes = array_merge(array('id' => $id, 'name' => $name), $attributes);
        $output .= $this->getOpenTag('select', $attributes);

        // Options.
        foreach ($options as $value => $text) {
            $optionAttributes = array('value' => $value);
            if ($this->compareValues($selected, $value)) {
                $optionAttributes['selected'] = true;
            }
            $output .= $this->getOpenTag('option', $optionAttributes);
            $output .= htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $output .= $this->getCloseTag('option');
        }

        // End tag.
        $output .= $this->getCloseTag('select');
        // Tag around select element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a list of radio buttons (input tag with type="radio").
     *
     * @param string $id
     *   The id attribute for all the radio buttons, required.
     * @param string $name
     *   The name attribute for all the radio buttons, required.
     * @param mixed|null $selected
     *   The selected value, null if no value has to be set to selected.
     * @param array $options
     *   The list of radio buttons as a keyed array, the keys being the value
     *   attribute of the radio button, the values being the label of the radio
     *   button.
     * @param array $attributes
     *   Any additional attributes to render on the div tag. The array is a keyed
     *   array, the keys being the attribute names, the values being the value of
     *   that attribute. If that value is an array it is rendered as a joined
     *   string of the values separated by a space (e.g. multiple classes).
     *
     * @return string The rendered radio buttons.
     * The rendered radio buttons.
     */
    protected function radio($id, $name, $selected, array $options, array $attributes = array())
    {
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
            $radioAttributes = $this->getRadioAttributes($id, $name, $value);
            $radioAttributes = $this->addAttribute($radioAttributes, 'required', $required);
            if ($this->compareValues($selected, $value)) {
                $radioAttributes['checked'] = true;
            }

            $output .= $this->getWrapper('radio1');
            $radioInput = $this->getOpenTag('input', $radioAttributes);
            if ($this->radioInputInLabel) {
                $output .= $this->renderLabel($text, $radioAttributes['id'], array(), false, $radioInput);
            } else {
                $output .= $radioInput;
                $output .= $this->renderLabel($text, $radioAttributes['id'], array(), false);
            }
            $output .= $this->getWrapperEnd('radio1');
        }

        // End tag.
        $output .= $this->getWrapperEnd('radio');
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a list of checkboxes (input tag with type="checkbox") enclosed in a
     * div.
     *
     * @param string $id
     *   The id prefix attribute for the checkboxes, required.
     * @param string $name
     *   The name attribute for all the checkboxes, required. When rendering
     *   multiple checkboxes, use a name that ends with [] for easy PHP processing.
     * @param array $selected
     *   The selected values.
     * @param array $options
     *   The list of checkboxes as a keyed array, the keys being the value
     *   attribute of the checkbox, the values being the label of the checkbox.
     * @param array $attributes
     *   Any additional attributes to render on the div tag. The array is a keyed
     *   array, the keys being the attribute names, the values being the value of
     *   that attribute. If that value is an array it is rendered as a joined
     *   string of the values separated by a space (e.g. multiple classes).
     *
     * @return string The rendered checkboxes.
     * The rendered checkboxes.
     */
    protected function checkbox($id, $name, array $selected, array $options, array $attributes = array())
    {
        $output = '';

        // Div tag.
        unset($attributes['required']);
        $output .= $this->getWrapper('input', $attributes);
        $output .= $this->getWrapper('checkbox', $attributes);

        // Checkboxes.
        foreach ($options as $value => $text) {
            $checkboxAttributes = $this->getCheckboxAttributes($id, $name, $value);
            if (in_array($value, $selected)) {
                $checkboxAttributes['checked'] = true;
            }
            $output .= $this->getWrapper('checkbox1');
            $checkboxInput = $this->getOpenTag('input', $checkboxAttributes);
            if ($this->checkboxInputInLabel) {
                $output .= $this->renderLabel($text, $checkboxAttributes['id'], array(), false, $checkboxInput);
            } else {
                $output .= $checkboxInput;
                $output .= $this->renderLabel($text, $checkboxAttributes['id'], array(), false);
            }
            $output .= $this->getWrapperEnd('checkbox1');
        }

        // End tag.
        $output .= $this->getWrapperEnd('checkbox');
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a markup (free format output) element.
     *
     * @param string $id
     *   The id attribute of the markup field.
     * @param string $name
     *   The name attribute of the markup field.
     * @param string $value
     *   The markup to render, may contain html, so don't escape. Will come from
     *   code not users.
     * @param array $attributes
     *   Any additional attributes to render for this field. The array is a
     *   keyed array, the keys being the attribute names, the values being the
     *   value of that attribute. If that value is an array it is rendered as a
     *   joined string of the values separated by a space (e.g. multiple
     *   classes).
     *
     * @return string
     *   The rendered markup.
     */
    protected function markup($id, $name, $value, array $attributes = array())
    {
        $attributes = array_merge(array('id' => $id, 'name' => $name), $attributes);
        $output = '';
        $output .= $this->getWrapper('markup', $attributes);
        $output .= $value;
        $output .= $this->getWrapperEnd('markup');
        return $output;
    }

    /**
     * @param string $type
     * @param array $attributes
     *
     * @return string
     */
    protected function getWrapper($type, array $attributes = array())
    {
        $tag = "{$type}WrapperTag";
        $class = "{$type}WrapperClass";
        $output = '';
        if (!empty($this->$tag)) {
            if (!empty($this->$class)) {
                $attributes = $this->addAttribute($attributes, 'class', $this->$class);
            }
            $output .= $this->getOpenTag($this->$tag, $attributes);
        }
        return $output;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getWrapperEnd($type)
    {
        $tag = "{$type}WrapperTag";
        $output = '';
        if (!empty($this->$tag)) {
            $output .= $this->getCloseTag($this->$tag);
        }
        return $output;
    }

    /**
     * Returns a secured html open tag string.
     *
     * @param string $tag
     *   The html tag.
     * @param array $attributes
     *   The attributes to render.
     * @param bool $selfClosing
     *   Whether the tag is self closing. Only in html4 this will add a /
     *   character before the closing > character.
     *
     * @return string
     *   The rendered open tag.
     */
    protected function getOpenTag($tag, array $attributes = array(), $selfClosing = false)
    {
        return '<' . htmlspecialchars($tag) . $this->renderAttributes($attributes) . ($selfClosing && !$this->html5 ? '/' : '') . '>';
    }

    /**
     * Returns a secured html close tag string.
     *
     * @param string $tag
     *   The html tag.
     *
     * @return string
     *   The rendered closing tag.
     */
    protected function getCloseTag($tag)
    {
        return '</' . htmlspecialchars($tag) .'>';
    }

    /**
     * Renders a list of attributes.
     *
     * @param array $attributes
     *
     * @return string
     *   html string with the rendered attributes and 1 space in front of it.
     */
    protected function renderAttributes(array $attributes)
    {
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            // Skip attributes that are not to be set (required, disabled, ...).
            if ($value !== false && $value !== '') {
                $attributeString .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                // HTML5: do not add a value to boolean attributes.
                // HTML4: add the name of the key as value for the attribute.
                if (!$this->html5 && $value === true) {
                    $value = $key;
                }
                if ($value !== true) {
                    $attributeString .= '="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '"';
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
    protected function addAttribute(array $attributes, $attribute, $value, $multiple = null)
    {
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
                } else {
                    // Set as an empty array
                    $attributes[$attribute] = array();
                }
                // Now we know for sure that it is an array, add it.
                $attributes[$attribute][] = $value;
            } else {
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
    protected function addLabelAttributes(array $attributes, $id)
    {
        $attributes = $this->addAttribute($attributes, 'for', $id);
        if (empty($id)) {
            $attributes = $this->addAttribute($attributes, 'class', $this->multiLabelClass);
        }
        return $attributes;
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function getCheckboxAttributes(/** @noinspection PhpUnusedParameterInspection */$id, $name, $value)
    {
        $checkboxAttributes = array(
            'type' => 'checkbox',
            'id' => "{$name}_{$value}",
            'name' => $value,
            'value' => 1,
        );
        return $checkboxAttributes;
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function getRadioAttributes($id, $name, $value)
    {
        $radioAttributes = array(
            'type' => 'radio',
            'id' => "{$id}_{$value}",
            'name' => $name,
            'value' => $value,
        );
        return $radioAttributes;
    }

    /**
     * Compares an option and a value to see if this option should be "selected".
     *
     * @param string|int|array $value
     * @param string|int $optionValue
     *
     * @return bool
     *   If this option equals the value.
     */
    protected function compareValues($value, $optionValue)
    {
        return is_array($value) ? in_array((string) $optionValue, $value) : (string) $optionValue === (string) $value;
    }
}
