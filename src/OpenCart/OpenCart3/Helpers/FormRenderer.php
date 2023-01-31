<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Siel\Acumulus\OpenCart\Helpers\FormRenderer as BaseFormRenderer;

/**
 * OC3 specific form render handling.
 */
class FormRenderer extends BaseFormRenderer
{
    public function __construct()
    {
        parent::__construct();
        // Default OpenCart 3 template seems to use html 5.
        $this->fieldsetWrapperClass = 'adminform';
        $this->legendWrapperClass = 'form-group';
        $this->summaryWrapperClass = 'form-group';
        $this->elementWrapperClass = 'form-group';
        $this->labelWrapperClass = 'form-group';
        $this->labelClass = ['col-sm-2', 'control-label'];
        $this->multiLabelClass = ['col-sm-2', 'control-label'];
        $this->descriptionWrapperClass = ['col-sm-offset-2', 'description'];
        $this->markupWrapperClass = ['col-sm-offset-2', 'message'];
    }
}
