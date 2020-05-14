<?php
/** @noinspection PhpUnused Many properties are used via property name
 *    construction.
 */

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
 * - All values (inputs) and texts between opening and closing tags are passed
 *   through {@see htmlspecialchars()}.
 * - The exceptions being:
 *     * A label prefix and postfix that come from code and may contain html.
 *       See {@see FormRenderer::renderLabel()}.
 *     * markup is rendered as is, as it may contain html (therefore its name
 *       markup ...). See {@see FormRenderer::markup()};
 * - All tags come from object properties or are hard coded and thus present no
 *   security risk but they are passed through htmlspecialchars() anyway. See
 *   {@see FormRenderer::getOpenTag()} and {@see FormRenderer::getCloseTag()}.
 * - All attributes, name and value are passed through htmlpecialchars(). See
 *   {@see FormRenderer::renderAttributes()}.
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
    protected $detailsWrapperTag = 'details';

    /** @var string */
    protected $detailsWrapperClass = '';

    /** @var string */
    protected $legendWrapperTag = 'legend';

    /** @var string */
    protected $legendWrapperClass = '';

    /** @var string */
    protected $summaryWrapperTag = 'summary';

    /** @var string */
    protected $summaryWrapperClass = '';

    /** @var string */
    protected $fieldsetDescriptionWrapperTag = 'div';

    /** @var string */
    protected $fieldsetDescriptionWrapperClass = 'fieldset-description';

    /**
     * Also used for details content.
     *
     * @var string
     */
    protected $fieldsetContentWrapperTag = '';

    /**
     * Also used for details content.
     *
     * @var string
     */
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
    protected $renderEmptyLabel = true;

    /** @var string */
    protected $labelTag = 'label';

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

    /** @var int */
    protected $htmlSpecialCharsFlag;

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
        if (property_exists($this, $property) && $property !== 'form') {
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
        $this->htmlSpecialCharsFlag = ENT_NOQUOTES;
        if (defined('ENT_HTML5')) {
            $this->htmlSpecialCharsFlag |= $this->html5 ? ENT_HTML5 : ENT_HTML401;
        }
        $this->form = $form;
        $this->form->addValues();
        return $this->renderFields($this->form->getFields());
    }

    /**
     * Renders a set of field definitions.
     *
     * @param array[] $fields
     *
     * @return string
     */
    protected function renderFields(array $fields)
    {
        $output = '';
        foreach ($fields as $id => $field) {
            // Add defaults.
            $field += array(
                'id' => $id,
                'name' => $id,
                'label' => '',
                'value' => '',
                'description' => '',
                'attributes' => array(),
                'options' => array(),
            );
            $output .= $this->renderField($field);
        }
        return $output;
    }

    /**
     * Renders 1 field definition (which may be a fieldset with multiple fields).
     *
     * @param array $field
     *   Array with the form field definition. the keys id, name, and attributes
     *   are expected to be set.
     *
     * @return string
     *   The rendered form field.
     */
    protected function renderField(array $field)
    {
        $output = '';
        $output .= !empty($field['fields']) ? $this->renderFieldset($field) : $this->renderSimpleField($field);
        return $output;
    }

    /**
     * Renders a fieldset or details form element.
     *
     * @param array $field
     *
     * @return string
     *   The rendered fieldset or details form element.
     */
    protected function renderFieldset(array $field)
    {
        $output = '';
        $output .= $this->fieldsetBegin($field);
        $output .= $this->renderFields($field['fields']);
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
        $output .= $this->getWrapper($field['type'], $field['attributes']);
        $titleTag = $field['type'] === 'fieldset' ? 'legend' : 'summary';
        if (!empty($field[$titleTag])) {
            $output .= $this->getWrapper($titleTag, $field['attributes']);
            $output .= $field[$titleTag];
            $output .= $this->getWrapperEnd($titleTag);
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
    protected function fieldsetEnd(array $field)
    {
        $output = '';
        $output .= $this->getWrapperEnd('fieldsetContent');
        $output .= $this->getWrapperEnd($field['type']);
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
    protected function renderSimpleField(array $field)
    {
        $output = '';

        // Split attributes over label and element.
        $attributes = $field['attributes'];
        $labelAttributes = array();
        if (!empty($attributes['label'])) {
            $labelAttributes = $attributes['label'];
            unset($attributes['label']);
        }
        if (!empty($attributes['required'])) {
            $labelAttributes['required'] = $attributes['required'];
        }
        $field['attributes'] = $attributes;

        if ($field['type'] !== 'hidden') {
            $output .= $this->getWrapper('element');
            // Do not use a <label> with an "id" attribute on the label for a
            // set of radio buttons, a set of checkboxes, or on markup.
            $id = in_array($field['type'], array('radio', 'checkbox', 'markup')) ? '' : $field['id'];
            $output .= $this->renderLabel($field['label'], $id, $labelAttributes, true);
            $output .= $this->getWrapper('inputDescription');
        }
        $output .= $this->renderElement($field);
        if ($field['type'] !== 'hidden') {
            $output .= $this->renderDescription($field['description']);
            $output .= $this->getWrapperEnd('inputDescription');
            $output .= $this->getWrapperEnd('element');
        }
        return $output;
    }

    /**
     * Renders a form field itself, ie without label and description.
     *
     * @param $field
     *
     * @return string
     *   The html for the form element.
     */
    protected function renderElement($field)
    {
        $type = $field['type'];
        switch ($type) {
            case 'textarea':
            case 'markup':
            case 'select':
            case 'radio':
            case 'checkbox':
                return $this->$type($field);
            default:
                return $this->input($field);
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
     * @param string $id
     *   The value of the for attribute. If the empty string, not a label tag
     *   but a span with a class="label" will be rendered.
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
    protected function renderLabel($text, $id, array $attributes, $wrapLabel, $prefix = '', $postfix = '')
    {
        $output = '';

        if ($this->renderEmptyLabel || !empty($text)) {
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
            $tag = empty($id) ? $this->multiLabelTag : $this->labelTag;
            $output .= $this->getOpenTag($tag, $attributes);
            $output .= $prefix . htmlspecialchars($text, $this->htmlSpecialCharsFlag, 'UTF-8') . $postfix;
            $output .= $this->getCloseTag($tag);

            // Tag around labels.
            if ($wrapLabel) {
                $output .= $this->getWrapperEnd('label');
            }
        }
        return $output;
    }

    /**
     * Renders an input field.
     *
     * @param array $field
     *
     * @return string
     *   The rendered input field.
     */
    protected function input(array $field)
    {
        $output = '';

        // Tag around input element.
        $output .= $this->getWrapper('input');

        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'type', $field['type']);
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $attributes = $this->addAttribute($attributes, 'value', $field['value']);
        $output .= $this->getOpenTag('input', $attributes, true);

        // Tag around input element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a textarea field.
     *
     * @param $field
     *
     * @return string
     *   The rendered textarea field.
     */
    protected function textarea(array $field)
    {
        $output = '';

        // Tag around input element.
        $output .= $this->getWrapper('input');
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getOpenTag('textarea', $attributes);
        $output .= htmlspecialchars($field['value'], $this->htmlSpecialCharsFlag, 'UTF-8');
        $output .= $this->getCloseTag('textarea');

        // Tag around input element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a markup (free format output) element.
     *
     * @param array $field
     *
     * @return string
     *   The rendered markup.
     */
    protected function markup(array $field)
    {
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output = '';
        $output .= $this->getWrapper('markup', $attributes);
        $output .= $field['value'];
        $output .= $this->getWrapperEnd('markup');
        return $output;
    }

    /**
     * Renders a select element.
     *
     * @param array $field
     *
     * @return string
     *   The rendered select element.
     */
    protected function select(array $field)
    {
        $output = '';

        // Tag around select element: same as for an input element.
        $output .= $this->getWrapper('input');

        // Select tag.
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getOpenTag('select', $attributes);

        // Options.
        foreach ($field['options'] as $value => $text) {
            $optionAttributes = array('value' => $value);
            if ($this->isOptionSelected($field['value'], $value)) {
                $optionAttributes['selected'] = true;
            }
            $output .= $this->getOpenTag('option', $optionAttributes);
            $output .= htmlspecialchars($text, $this->htmlSpecialCharsFlag, 'UTF-8');
            $output .= $this->getCloseTag('option');
        }

        // End tag.
        $output .= $this->getCloseTag('select');
        // Tag around select element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a list of radio buttons.
     *
     * @param array $field
     *
     * @return string
     *   The rendered radio buttons.
     */
    protected function radio(array $field)
    {
        $output = '';

        // Handling of required attribute: may appear on all radio buttons with
        // the same name.
        $attributes = $field['attributes'];
        $required = !empty($attributes['required']);
        unset($attributes['required']);

        // Tag(s) around radio buttons.
        $output .= $this->getWrapper('input', $attributes);
        $output .= $this->getWrapper('radio', $attributes);

        // Radio buttons.
        foreach ($field['options'] as $value => $text) {
            $radioAttributes = $this->getRadioAttributes($field['id'], $field['name'], $value);
            $radioAttributes = $this->addAttribute($radioAttributes, 'required', $required);
            if ($this->isOptionSelected($field['value'], $value)) {
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
     * Renders a list of checkboxes.
     *
     * @param array $field
     *
     * @return string
     *   The rendered checkboxes.
     */
    protected function checkbox(array $field)
    {
        $output = '';

        // Div tag.
        $attributes = $field['attributes'];
//?        unset($attributes['required']);

        $output .= $this->getWrapper('input', $attributes);
        $output .= $this->getWrapper('checkbox', $attributes);

        // Checkboxes.
        foreach ($field['options'] as $value => $text) {
            $checkboxAttributes = $this->getCheckboxAttributes($field['id'], $field['name'], $value);
            if (in_array($value, $field['value'], false)) {
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
     * Returns the open tag for a wrapper element.
     *
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
     * Returns the closing tag for a wrapper element.
     *
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
        return '<' . htmlspecialchars($tag, ENT_QUOTES, 'ISO-8859-1') . $this->renderAttributes($attributes) . ($selfClosing && !$this->html5 ? '/' : '') . '>';
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
        return '</' . htmlspecialchars($tag, ENT_QUOTES, 'ISO-8859-1') .'>';
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
     * Adds (or overwrites) an attribute.
     *
     * If the attribute already exists and $multiple is false, the existing
     * value will be overwritten. If it is true, or null while $attribute is
     * 'class', it will be added.
     *
     * @param array $attributes
     *   The array of attributes to add the value to.
     * @param string $attribute
     *   The name of the attribute to set.
     * @param string $value
     *   The value of the attribute to add or set.
     * @param bool|null $multiple
     *   Allow multiple values for the given attribute. By default (null) this
     *   is only allowed for the class attribute.
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
     * Adds a set of attributes specific for a label.
     *
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
     * Returns a set of attributes for a single checkbox.
     *
     * @param string $id
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function getCheckboxAttributes(/** @noinspection PhpUnusedParameterInspection */$id, $name, $value)
    {
        return array(
            'type' => 'checkbox',
            'id' => "{$name}_{$value}",
            'name' => $value,
            'value' => 1,
        );
    }

    /**
     * Returns a set of attributes for a single radio button.
     *
     * @param string $id
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function getRadioAttributes($id, $name, $value)
    {
        return array(
            'type' => 'radio',
            'id' => "{$id}_{$value}",
            'name' => $name,
            'value' => $value,
        );
    }

    /**
     * Returns whether an option is part of a set of selected values.
     *
     * @param string|int|array $selectedValues
     *   The set of selected values, may be just 1 scalar value.
     * @param string|int $option
     *   The option to search for in the set of selected values.
     *
     * @return bool
     *   If this option is part of the selected values.
     */
    protected function isOptionSelected($selectedValues, $option)
    {
        return is_array($selectedValues) ? in_array((string) $option, $selectedValues,false) : (string) $option === (string) $selectedValues;
    }
}
