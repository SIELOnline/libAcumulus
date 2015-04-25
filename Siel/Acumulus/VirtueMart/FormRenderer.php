<?php
namespace Siel\Acumulus\VirtueMart;

use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer
 */
class FormRenderer extends BaseFormRenderer {

  /**
   * Constructor.
   */
  public function __construct() {
    // Default Joomla template seems to use xhtml.
    $this->html5 = false;
    $this->fieldsetWrapperClass = 'adminform';
    $this->elementWrapperClass = 'control-group';
    $this->requiredMarkup = '<span class="star"> *</span>';
    $this->labelWrapperTag = 'div';
    $this->labelWrapperClass = 'control-label';
    $this->inputWrapperTag = 'div';
    $this->inputWrapperClass = 'controls';
    $this->multiLabelClass = 'control-label';
    $this->descriptionClass = 'controls';
  }

}
