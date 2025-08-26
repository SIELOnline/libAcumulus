<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use function defined;
use function in_array;
use function is_array;

/**
 * Provides form element rendering functionality. This basic implementation renders the
 * elements as wrapped HTML input elements. To comply with shop-specific styling, it is
 * supposed to be overridden per shop that uses this way of rendering. For now those are:
 * HikaShop/VirtueMart (Joomla), OpenCart, and WooCommerce (WordPress).
 *
 * SECURITY REMARKS
 * ----------------
 * - All values (inputs) and texts between opening and closing tags are passed
 *   through {@see htmlspecialchars()}.
 * - The exceptions being:
 *     * A label prefix and postfix that come from code and may contain HTML.
 *       See {@see renderLabel()}.
 *     * Label text if indicated as containing HTML by a label attribute 'html'
 *       (which comes from code).
 *     * markup is rendered as is, as it may contain HTML (therefore its name
 *       markup ...). See {@see markup()};
 * - All tags come from object properties or are hard coded and thus present no
 *   security risk, but they are passed through {@see htmlspecialchars()}
 *   anyway. See {@see getOpenTag()} and {@see getCloseTag()}.
 * - All attributes, name and value are passed through {@see htmlpecialchars()}.
 *   See {@see renderAttributes()}.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class FormRenderer
{
    public const RequiredMarkup = '<span class="required">*</span>';

    protected bool $html5 = true;
   protected string $elementWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $elementWrapperClass = 'form-element';
    protected string $fieldsetWrapperTag = 'fieldset';
    /** @var string|string[] */
    protected string|array $fieldsetWrapperClass = '';
    protected string $detailsWrapperTag = 'details';
    /** @var string|string[] */
    protected string|array $detailsWrapperClass = '';
    protected string $legendWrapperTag = 'legend';
    /** @var string|string[] */
    protected string|array $legendWrapperClass = '';
    protected string $summaryWrapperTag = 'summary';
    /** @var string|string[] */
    protected string|array $summaryWrapperClass = '';
    protected string $fieldsetDescriptionWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $fieldsetDescriptionWrapperClass = 'fieldset-description';
    /** Also used for details content. */
    protected string $fieldsetContentWrapperTag = '';
    /**
     * @var string|string[]
     *   Also used for details content.
     */
    protected string|array $fieldsetContentWrapperClass = 'fieldset-content';
    protected string $labelWrapperTag = '';
    /** @var string|string[] */
    protected string|array $labelWrapperClass = '';
    protected string $markupWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $markupWrapperClass = 'message';
    protected string $inputDescriptionWrapperTag = '';
    /** @var string|string[] */
    protected string|array $inputDescriptionWrapperClass = '';
    protected string $inputWrapperTag = '';
    /** @var string|string[] */
    protected string|array $inputWrapperClass = '';
    protected string $radioWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $radioWrapperClass = 'radio';
    protected string $radio1WrapperTag = '';
    /** @var string|string[] */
    protected string|array $radio1WrapperClass = '';
    protected string $checkboxWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $checkboxWrapperClass = 'checkbox';
    protected string $checkbox1WrapperTag = '';
    /** @var string|string[] */
    protected string|array $checkbox1WrapperClass = '';
    protected bool $renderEmptyLabel = true;
    protected string $labelTag = 'label';
    protected string $multiLabelTag = 'label';
    /** @var string|string[] */
    protected string|array $labelClass = '';
    /** @var string|string[] */
    protected string|array $multiLabelClass = '';
    protected string $descriptionWrapperTag = 'div';
    /** @var string|string[] */
    protected string|array $descriptionWrapperClass = 'description';
    protected bool $radioInputInLabel = false;
    protected bool $checkboxInputInLabel = false;
    protected string $requiredMarkup = self::RequiredMarkup;
    protected bool $usePopupDescription = false;
    protected int $htmlSpecialCharsFlag;
    protected Form $form;

    /**
     * Sets the value of a property of this object.
     * The property must exist as property
     *
     * @return $this
     */
    public function setProperty(string $property, $value): self
    {
        if (property_exists($this, $property) && $property !== 'form') {
            /** @noinspection PhpVariableVariableInspection */
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * Renders the form.
     *
     * @return string
     *   The HTML for the form.
     */
    public function render(Form $form): string
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
     */
    protected function renderFields(array $fields): string
    {
        $output = '';
        foreach ($fields as $id => $field) {
            // Add defaults.
            $field += [
                'id' => $id,
                'name' => $id,
                'label' => '',
                'value' => '',
                'description' => '',
                'attributes' => [],
                'options' => [],
            ];
            $output .= $this->renderField($field);
        }
        return $output;
    }

    /**
     * Renders 1 field definition (which may be a fieldset with multiple fields).
     *
     * @param array $field
     *   Array with the form field definition. the keys 'id', 'name', and
     *  'attributes' are expected to be set.
     */
    protected function renderField(array $field): string
    {
        return $this->isFieldset($field) ? $this->renderFieldset($field) : $this->renderSimpleField($field);
    }

    /**
     * Renders a <fieldset> or <details> form element.
     */
    protected function renderFieldset(array $field): string
    {
        $output = $this->fieldsetBegin($field);
        $output .= $this->renderFields($field['fields']);
        $output .= $this->fieldsetEnd($field);
        return $output;
    }

    /**
     * Outputs the beginning of a fieldset or details.
     *
     * The beginning constitutes:
     * - The <fieldset> or <details> tag.
     * - The <legend> or <summary> tag.
     * - A wrapper tag for the fieldset contents.
     * - The description for the fieldset.
     */
    protected function fieldsetBegin(array $field): string
    {
        $wrapperAttributes = $field['attributes'] + ['id' => $field['id']];
        $output = $this->getWrapper($field['type'], $wrapperAttributes);
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
     * The end constitutes:
     * - A wrapper closing tag for the fieldset contents.
     * - The </fieldset> or </details> tag.
     */
    protected function fieldsetEnd(array $field): string
    {
        $output = $this->getWrapperEnd('fieldsetContent');
        $output .= $this->getWrapperEnd($field['type']);
        return $output;
    }

    /**
     * Renders a form field including its label and description.
     */
    protected function renderSimpleField(array $field): string
    {
        $output = '';

        // Split attributes over label and element.
        $attributes = $field['attributes'];
        $labelAttributes = [];
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
            $id = in_array($field['type'], ['radio', 'checkbox']) ? '' : $field['id'];
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
     * Renders a form field itself, i.e. without label and description.
     */
    protected function renderElement($field): string
    {
        $type = $field['type'];
        return match ($type) {
            'textarea', 'markup', 'select', 'radio', 'checkbox', 'collection' => $this->$type($field),
            default => $this->input($field),
        };
    }

    /**
     * Renders a collection of elements.
     *
     * A collection differs from a fieldset or details in that only 1 'label'
     * and 'description' will be rendered and that the collection of fields
     * will be rendered as 1 element, that is:
     * - No labels or descriptions for the subfields.
     * - No form element markup around the subfields.
     * - Input element specific wrappers will be rendered, but this is often
     *   empty, except for selections (select, radio, checkboxes).
     */
    protected function collection(array $field): string
    {
        $output = '';

        $oldMarkupWrapperTag = $this->markupWrapperTag;
        $this->markupWrapperTag = '';
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getWrapper('collection', $attributes);
        foreach ($field['fields'] as $id => $subField) {
            $subField += [
                'id' => $id,
                'name' => $id,
                'label' => '',
                'value' => '',
                'description' => '',
                'attributes' => [],
                'options' => [],
            ];
            $output .= $this->renderElement($subField);
        }
        $output .= $this->getWrapperEnd('collection');
        $this->markupWrapperTag = $oldMarkupWrapperTag;

        return $output;
    }

    /**
     * Renders a descriptive help text.
     */
    protected function renderDescription(string $text, bool $isFieldset = false): string
    {
        $output = '';

        // Help text.
        if (!empty($text)) {
            // Allow for HTML links in the help text, so no filtering.
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
     *   The text for the label.
     * @param string $id
     *   The value of the for attribute. If it is the empty string, not a label tag
     *   but a span with a class="label" is rendered.
     * @param array $attributes
     *   Any additional attributes to render for the label. The array is a keyed
     *   array, the keys being the attribute names, the values being the
     *   value of that attribute. If that value is an array, it is rendered as a
     *   joined string of the values separated by a space (e.g. multiple classes).
     * @param bool $wrapLabel
     *   Whether to wrap this label within the defined label wrapper tag.
     * @param string $prefix
     *   Prefix to prepend to the label text, may contain HTML, so don't escape.
     *   Will come from code, not users.
     * @param string $postfix
     *   Postfix to append to the label text, may contain HTML, so don't escape.
     *   Will come from code, not users.
     *
     * @return string The rendered label.
     *   The rendered label.
     */
    protected function renderLabel(
        string $text,
        string $id,
        array $attributes,
        bool $wrapLabel,
        string $prefix = '',
        string $postfix = ''
    ): string {
        $output = '';
        if ($this->renderEmptyLabel || !empty($text)) {
            // Split attributes over label and wrapper.
            $wrapperAttributes = [];
            if (!empty($attributes['wrapper'])) {
                $wrapperAttributes = $attributes['wrapper'];
                unset($attributes['wrapper']);
            }
            $required = false;
            if (isset($attributes['required'])) {
                $wrapperAttributes['required'] = $attributes['required'];
                $required = (bool) $attributes['required'];
                // Required is not an allowed attribute for a label, remove it as an
                // attribute.
                unset($attributes['required']);
            }

            // Tag around main labels.
            if ($wrapLabel) {
                $output .= $this->getWrapper('label', $wrapperAttributes);
            }

            // Label.
            $allowHtml = !empty($attributes['html']);
            unset($attributes['html']);
            $attributes = $this->addLabelAttributes($attributes, $id);
            $postfix .= $required ? $this->requiredMarkup : '';
            $tag = empty($id) ? $this->multiLabelTag : $this->labelTag;
            $output .= $this->getOpenTag($tag, $attributes);
            $output .= $prefix;
            $output .= $allowHtml ? $text : htmlspecialchars($text, $this->htmlSpecialCharsFlag, 'UTF-8');
            $output .= $postfix;
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
     */
    protected function input(array $field): string
    {
        $output = '';

        // Tag around input element.
        if ($field['type'] !== 'hidden') {
            $output .= $this->getWrapper('input');
        }

        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'type', $field['type']);
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $attributes = $this->addAttribute($attributes, 'value', $field['value']);
        $output .= $this->getOpenTag('input', $attributes, true);

        // Tag around input element.
        if ($field['type'] !== 'hidden') {
            $output .= $this->getWrapperEnd('input');
        }

        return $output;
    }

    /**
     * Renders a textarea field.
     */
    protected function textarea(array $field): string
    {
        $output = '';

        // Tag around input element.
        $output .= $this->getWrapper('input');
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getOpenTag('textarea', $attributes);
        $output .= htmlspecialchars((string) $field['value'], $this->htmlSpecialCharsFlag, 'UTF-8');
        $output .= $this->getCloseTag('textarea');

        // Tag around input element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a markup (free format output) element.
     */
    protected function markup(array $field): string
    {
        $output = '';

        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getWrapper('markup', $attributes);
        if (!empty($field['value'])) {
            $output .= $field['value'];
        }
        $output .= $this->getWrapperEnd('markup');

        return $output;
    }

    /**
     * Renders a select element.
     */
    protected function select(array $field): string
    {
        $output = '';

        // Tag around the 'select' element: the same as for an input element.
        $output .= $this->getWrapper('input');

        // The 'select' tag.
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getOpenTag('select', $attributes);

        // Options.
        foreach ($field['options'] as $value => $text) {
            $optionAttributes = ['value' => $value];
            if ($this->isOptionSelected($field['value'], $value)) {
                $optionAttributes['selected'] = true;
            }
            $output .= $this->getOpenTag('option', $optionAttributes);
            $output .= htmlspecialchars((string) $text, $this->htmlSpecialCharsFlag, 'UTF-8');
            $output .= $this->getCloseTag('option');
        }

        // End tag.
        $output .= $this->getCloseTag('select');
        // Tag around the 'select' element.
        $output .= $this->getWrapperEnd('input');

        return $output;
    }

    /**
     * Renders a list of radio buttons.
     */
    protected function radio(array $field): string
    {
        $output = '';

        // Handling of the 'required' attribute: may appear on all radio buttons with
        // the same name.
        $attributes = $field['attributes'];
        $required = !empty($attributes['required']);
        unset($attributes['required']);

        // Tag(s) around radio buttons.
        $output .= $this->getWrapper('input', $attributes);
        $output .= $this->getWrapper('radio', $attributes + ['id' => $field['id']]);

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
                $output .= $this->renderLabel($text, $radioAttributes['id'], [], false, $radioInput);
            } else {
                $output .= $radioInput;
                $output .= $this->renderLabel($text, $radioAttributes['id'], [], false);
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
     */
    protected function checkbox(array $field): string
    {
        $output = '';

        // Div tag.
        $attributes = $field['attributes'];
//?        unset($attributes['required']);

        $output .= $this->getWrapper('input', $attributes);
        $output .= $this->getWrapper('checkbox', $attributes + ['id' => $field['id']]);

        // Checkboxes.
        foreach ($field['options'] as $value => $text) {
            $checkboxAttributes = $this->getCheckboxAttributes($field['id'], $field['name'], $value);
            if (in_array($value, $field['value'], false)) {
                $checkboxAttributes['checked'] = true;
            }
            $output .= $this->getWrapper('checkbox1');
            $checkboxInput = $this->getOpenTag('input', $checkboxAttributes);
            if ($this->checkboxInputInLabel) {
                $output .= $this->renderLabel($text, $checkboxAttributes['id'], [], false, $checkboxInput);
            } else {
                $output .= $checkboxInput;
                $output .= $this->renderLabel($text, $checkboxAttributes['id'], [], false);
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
     * @noinspection PhpVariableVariableInspection
     */
    protected function getWrapper(string $type, array $attributes = []): string
    {
        $tag = "{$type}WrapperTag";
        $class = "{$type}WrapperClass";
        $output = '';
        if (!empty($this->$tag)) {
            if (!empty($this->$class)) {
                $attributes = $this->addAttribute($attributes, 'class', $this->$class);
            }
            if (isset($attributes['required'])) {
                // Required is not an allowed attribute for a wrapper, remove it as an
                // attribute.
                unset($attributes['required']);
            }
            $output .= $this->getOpenTag($this->$tag, $attributes);
        }
        return $output;
    }

    /**
     * Returns the closing tag for a wrapper element.
     *
     * @noinspection PhpVariableVariableInspection
     */
    protected function getWrapperEnd(string $type): string
    {
        $tag = "{$type}WrapperTag";
        $output = '';
        if (!empty($this->$tag)) {
            $output .= $this->getCloseTag($this->$tag);
        }
        return $output;
    }

    /**
     * Returns a secure HTML open tag string.
     *
     * @param string $tag
     *   The HTML tag.
     * @param array $attributes
     *   The attributes to render.
     * @param bool $selfClosing
     *   Whether the tag is self-closing. Only in HTML4 this will add a /
     *   character before the closing > character.
     *
     * @return string
     *   The rendered open tag.
     */
    protected function getOpenTag(string $tag, array $attributes = [], bool $selfClosing = false): string
    {
        if (in_array($tag, ['div', 'span'])) {
            // A <div> or <span> should not get a name attribute.
            unset($attributes['name']);
        }
        return '<' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . $this->renderAttributes($attributes) . ($selfClosing && !$this->html5 ?
                '/' : '') . '>';
    }

    /**
     * Returns a secure HTML close tag string.
     *
     * @param string $tag
     *   The HTML tag.
     *
     * @return string
     *   The rendered closing tag.
     */
    protected function getCloseTag(string $tag): string
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
    protected function renderAttributes(array $attributes): string
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
                // HTML4: add the name of the key as the value for the attribute.
                if (!$this->html5 && $value === true) {
                    $value = $key;
                }
                if ($value !== true) {
                    $attributeString .= '="' . htmlspecialchars((string) $value, ENT_COMPAT, 'UTF-8') . '"';
                }
            }
        }
        return $attributeString;
    }

    /**
     * Adds (or overwrites) an attribute.
     * If the attribute already exists and $multiple is false, the existing
     * value will be overwritten. If it is true, or null while $attribute is
     * 'class', it will be added.
     *
     * @param array $attributes
     *   The array of attributes to add the value to.
     * @param string $attribute
     *   The name of the attribute to set.
     * @param bool|string|string[]|int|null $value
     *   The value(s) of the attribute to add or set. If null, the attribute value will
     *   be ignored (no-op). Other types must be convertable to a string because that is
     *   how an attribute value eventually will end. For array and bool we do some
     *   handling our own, for other types we expect that a cast to string suffices.
     * @param bool|null $multiple
     *   Allow multiple values for the given attribute. By default, (null) this
     *   is only allowed for the class attribute.
     *
     * @return array
     *   The set of attributes with the value added.
     */
    protected function addAttribute(array $attributes, string $attribute, mixed $value, ?bool $multiple = null): array
    {
        // Do add false and 0, but not an empty string, empty array or null.
        if ($value !== null && $value !== '' && $value !== []) {
            if ($multiple === null) {
                $multiple = is_array($value) || $attribute === 'class';
            }

            if ($multiple) {
                // Multiple values allowed: set or add, not overwriting.
                if (isset($attributes[$attribute])) {
                    // Assure it is an array, not a scalar
                    $attributes[$attribute] = (array) $attributes[$attribute];
                } else {
                    // Set as an empty array
                    $attributes[$attribute] = [];
                }
                // Now we know for sure that it is an array, add it.
                $attributes[$attribute] = array_merge($attributes[$attribute], (array) $value);
            } else {
                // Single value: just set, possibly overwriting.
                $attributes[$attribute] = $value;
            }
        }
        return $attributes;
    }

    /**
     * Adds a set of attributes specific for a label.
     */
    protected function addLabelAttributes(array $attributes, string $id): array
    {
        $attributes = $this->addAttribute($attributes, 'for', $id);
        if (!empty($id)) {
            $attributes = $this->addAttribute($attributes, 'class', $this->labelClass);
        } else {
            $attributes = $this->addAttribute($attributes, 'class', $this->multiLabelClass);
        }
        return $attributes;
    }

    /**
     * Returns a set of attributes for a single checkbox.
     */
    protected function getCheckboxAttributes(
        /** @noinspection PhpUnusedParameterInspection */ string $id,
        string $name,
        string $value
    ): array {
        return [
            'type' => 'checkbox',
            'id' => "{$name}_$value",
            'name' => $value,
            'value' => 1,
        ];
    }

    /**
     * Returns a set of attributes for a single radio button.
     */
    protected function getRadioAttributes(string $id, string $name, int|string $value): array
    {
        return [
            'type' => 'radio',
            'id' => "{$id}_$value",
            'name' => $name,
            'value' => (string) $value,
        ];
    }

    /**
     * Returns whether an option is part of a set of selected values.
     *
     * @param int|string|array $selectedValues
     *   The set of the selected values, it may be just 1 scalar value.
     * @param int|string $option
     *   The option to search for in the set of selected values.
     *
     * @return bool
     *   If this option is part of the selected values.
     */
    protected function isOptionSelected(int|string|array $selectedValues, int|string $option): bool
    {
        return is_array($selectedValues) ? in_array((string) $option, $selectedValues,false) : (string) $option === (string) $selectedValues;
    }

    /**
     * Returns whether the element is a 'fieldset' or 'details' element.
     *
     * Note that a collection is handled as a simple element.
     */
    public function isFieldset(array $field): bool
    {
        return in_array($field['type'], ['fieldset', 'details']);
    }
}
