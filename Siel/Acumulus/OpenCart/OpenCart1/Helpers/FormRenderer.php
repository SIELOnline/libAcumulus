<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;

use Siel\Acumulus\OpenCart\Helpers\FormRenderer as BaseFormRenderer;
use Siel\Acumulus\OpenCart\Helpers\Registry;

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
