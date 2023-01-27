<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Siel\Acumulus\OpenCart\Helpers\FormRenderer as BaseFormRenderer;

use function in_array;
use function is_string;

/**
 * OC4 specific form render handling.
 */
class FormRenderer extends BaseFormRenderer
{
    public function __construct()
    {
        parent::__construct();
        $this->elementWrapperClass = 'row mb-3';
        $this->labelClass = ['col-sm-2', 'col-form-label'];
        $this->multiLabelClass = ['col-sm-2', 'col-form-label'];
        $this->inputWrapperTag = 'div';
        $this->inputWrapperClass = 'col-sm-10';
        $this->descriptionWrapperClass = ['offset-sm-2', 'col-sm-10', 'form-text'];
        $this->markupWrapperClass = ['offset-sm-2', 'col-sm-10', 'message'];
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
