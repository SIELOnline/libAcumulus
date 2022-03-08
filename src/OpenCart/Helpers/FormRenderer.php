<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like an OpenCart form.
 */
class FormRenderer extends BaseFormRenderer
{
    public function __construct()
    {
        // Default OpenCart template seems to use html 5.
        $this->fieldsetWrapperClass = 'adminform';
        $this->legendWrapperClass = 'form-group';
        $this->summaryWrapperClass = 'form-group';
        $this->requiredMarkup = '';
        $this->inputWrapperClass = 'form-control';

        $this->elementWrapperClass = 'form-group';
        $this->labelWrapperClass = 'form-group';
        $this->labelClass = ['col-sm-2', 'control-label'];
        $this->multiLabelClass = ['col-sm-2', 'control-label'];
        $this->descriptionWrapperClass = 'col-sm-offset-2 description';
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
    protected function handleRequired(array $field)
    {
        if (!empty($field['attributes']['required'])) {
            if (empty($this->elementWrapperClass)) {
                $this->elementWrapperClass = 'required';
            } else {
                $this->elementWrapperClass = (array) $this->elementWrapperClass;
                $this->elementWrapperClass[] = 'required';
            }
        }
    }
}
