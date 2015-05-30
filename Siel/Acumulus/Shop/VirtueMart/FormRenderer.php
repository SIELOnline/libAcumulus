<?php
namespace Siel\Acumulus\Shop\VirtueMart;

use JHtml;
use Siel\Acumulus\Helpers\Form;
use \Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;
use vmJsApi;

/**
 * Class FormRenderer
 */
class FormRenderer extends BaseFormRenderer {

  /**
   * Constructor.
   *
   * @param Form $form
   */
  public function __construct(Form $form) {
    parent::__construct($form);

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

  public function input($type, $name, $value = '', array $attributes = array()) {
    $output = '';
    if ($type === 'date') {
      // @todo: can we use vmJsApi::jDate?
      $output .= $this->getWrapper('input');
      $output .= JHTML::calendar($value, $name, $name, $this->form->getShopDateFormat()/*, $attributes*/);
      //$output .= vmJsApi::jDate($value, $name, $name);
      $output .= $this->getWrapperEnd('input');
    }
    else {
      $output .= parent::input($type, $name, $value, $attributes);
    }
    return $output;
  }

}
