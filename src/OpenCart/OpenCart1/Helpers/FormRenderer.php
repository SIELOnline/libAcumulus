<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;

use Siel\Acumulus\OpenCart\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like an OpenCart 1 form.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->labelWrapperClass = '';
        $this->radioWrapperClass = 'form-element-radios';
        $this->checkboxWrapperClass = 'form-element-checkboxes';
        $this->multiLabelClass = 'label';
        $this->descriptionWrapperClass = 'desc';
        $this->fieldsetDescriptionWrapperClass = 'fieldset-desc desc';
        $this->requiredMarkup = static::RequiredMarkup;
    }

    /**
     * {@inheritdoc}
     *
     * In OC1 required fields are handled differently, no action needed here.
     */
    protected function handleRequired(array $field)
    {
    }
}
