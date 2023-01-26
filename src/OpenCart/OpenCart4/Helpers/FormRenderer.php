<?php
/**
 * @noinspection DuplicatedCode  Remove when extracting code common for OC3 and OC4
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

use function in_array;
use function is_string;

/**
 * FormRenderer renders an Acumulus form definition like an OpenCart form.
 */
class FormRenderer extends BaseFormRenderer
{
    public function __construct()
    {
        $this->requiredMarkup = '';
        $this->elementWrapperClass = 'row mb-3';
        $this->labelClass = ['col-sm-2', 'col-form-label'];
        $this->multiLabelClass = ['col-sm-2', 'col-form-label'];
        $this->inputWrapperTag = 'div';
        $this->inputWrapperClass = 'col-sm-10';
        $this->descriptionWrapperClass = ['offset-sm-2', 'col-sm-10', 'form-text'];
        $this->markupWrapperClass = ['offset-sm-2', 'col-sm-10', 'message'];
    }

    /**
     * {@inheritdoc}
     */
    protected function renderSimpleField(array $field): string
    {
        $oldElementWrapperClass = $this->elementWrapperClass;
        $this->handleRequired($field);
        $result = parent::renderSimpleField($field);
        $this->elementWrapperClass = $oldElementWrapperClass;
        return $result;
    }

    /**
     * Handles required fields.
     *
     * @param array $field
     */
    protected function handleRequired(array $field): void
    {
        if (!empty($field['attributes']['required'])) {
            if (empty($this->elementWrapperClass)) {
                $this->elementWrapperClass = 'required';
            } else {
                $this->elementWrapperClass = (array) $this->elementWrapperClass;
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $this->elementWrapperClass[] = 'required';
            }
        }
    }

    protected function input(array $field): string
    {
        // Tag around input element.
        if (!in_array($field['type'], ['hidden', 'button'])) {
            if (empty($field['attributes']['class'])) {
                $field['attributes']['class'] = [];
            } elseif (is_string($field['attributes']['class'])) {
                $field['attributes']['class'] = (array) $field['attributes']['class'];
            }
            $field['attributes']['class'][] = 'form-control';
        }
        return parent::input($field);
    }

    protected function select(array $field): string
    {
        // Tag around input element.
        if ($field['type'] !== 'hidden') {
            if (empty($field['attributes']['class'])) {
                $field['attributes']['class'] = [];
            } elseif (is_string($field['attributes']['class'])) {
                $field['attributes']['class'] = (array) $field['attributes']['class'];
            }
            $field['attributes']['class'][] = 'form-select';
        }
        return parent::select($field);
    }
}
