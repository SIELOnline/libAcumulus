<?php
namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormMapper maps an Acumulus form definition to a OpenCart 3 form definition.
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
        $this->elementWrapperClass = 'form-group';
        $this->requiredMarkup = '';
        $this->labelWrapperClass = 'form-group';
        $this->inputWrapperClass = 'form-control';
        $this->multiLabelClass = 'control-label';
        $this->descriptionClass = 'col-sm-offset-2 description';
    }

    protected function renderField(array $field)
    {
        $oldElementWrapperClass = $this->elementWrapperClass;
        if (!empty($field['attributes']['required'])) {
            if (empty($this->elementWrapperClass)) {
                $this->elementWrapperClass = '';
            } else {
                $this->elementWrapperClass .= ' ';
            }
            $this->elementWrapperClass .= 'required';
        }
        $result = parent::renderField($field);
        $this->elementWrapperClass = $oldElementWrapperClass;
        return $result;
    }


    protected function addLabelAttributes(array $attributes, $id)
    {
        $attributes = parent::addLabelAttributes($attributes, $id);
        $attributes = $this->addAttribute($attributes, 'class', 'col-sm-2');
        $attributes = $this->addAttribute($attributes, 'class', 'control-label');
        return $attributes;
    }
}
