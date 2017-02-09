<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormMapper maps an Acumulus form definition to a OpenCart 1 form definition.
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
        $this->labelWrapperClass = '';
        $this->inputWrapperClass = 'form-control';
        $this->radioWrapperClass = 'form-element-radios';
        $this->checkboxWrapperClass = 'form-element-checkboxes';
        $this->multiLabelClass = 'label';
        $this->descriptionClass = 'desc';
    }

    /**
     * {@inheritdoc}
     */
    protected function fieldsetBegin(array $field) {
        $oldDescriptionClass = $this->descriptionClass;
        $this->descriptionClass = 'fieldset-desc desc';
        $result = parent::fieldsetBegin($field);
        $this->descriptionClass = $oldDescriptionClass;
        return $result;
    }
}
