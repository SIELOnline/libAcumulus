<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like an OpenCart form.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Default OpenCart template seems to use html 5.
        $this->fieldsetWrapperClass = 'adminform';
        $this->legendWrapperClass = 'form-group';
        $this->requiredMarkup = '';
        $this->inputWrapperClass = 'form-control';

        $this->elementWrapperClass = 'form-group';
        $this->labelWrapperClass = 'form-group';
        $this->multiLabelClass = 'control-label';
        $this->descriptionWrapperClass = 'col-sm-offset-2 description';
    }

    /**
     * {@inheritdoc}
     */
    protected function renderField(array $field)
    {
        $oldElementWrapperClass = $this->elementWrapperClass;
        $this->handleRequired($field);
        $result = parent::renderField($field);
        $this->elementWrapperClass = $oldElementWrapperClass;
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function addLabelAttributes(array $attributes, $id)
    {
        $attributes = parent::addLabelAttributes($attributes, $id);
        $attributes = $this->addAttribute($attributes, 'class', 'col-sm-2');
        $attributes = $this->addAttribute($attributes, 'class', 'control-label');
        return $attributes;
    }

    /**
     * Handles required fields.
     *
     * @param array $field
     */
    protected function handleRequired(array $field)
    {
        if (!empty($field['attributes']['required'])) {
            if (empty($this->elementWrapperClass)) {
                $this->elementWrapperClass = '';
            } else {
                $this->elementWrapperClass .= ' ';
            }
            $this->elementWrapperClass .= 'required';
        }
    }
}
