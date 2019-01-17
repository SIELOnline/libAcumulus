<?php
namespace Siel\Acumulus\MyWebShop\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like a MyWebShop form.
 *
 * @todo: delete ths file if MyWebshop provides its own form subsystem,
 *   use a FormMapper in that case.
 * @todo: setting all the properties might not be enough to get a form that
 *  looks like the other forms in MyWebshop. In that case you probably have
 *  to change the generated html. If so, override the necessary methods.
 *
 * Note: beware of possible security issues:
 * - render tags with the methods getOpenTag() and getCloseTag().
 * - render attributes with the method renderAttributes.
 * - pass plain text through htmlspecialchars().
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // @todo: define if MyWebshop's templating system renders html5.
        $this->html5 = true;

        // @todo: define tags and classes to use around form elements like a fieldset, details, legend, summary, label, input, radio button input, select and description.
        // See the base class for all properties.
        $this->legendWrapperClass = 'form-group';
        // @todo: required markup no longer necessary in html5
        $this->requiredMarkup = '';

        // @todo: these are just examples of the classes used in (various) webshops. Check the forms in your own webshop.
        $this->inputWrapperClass = 'form-control';
        $this->elementWrapperClass = 'form-group';
        $this->labelWrapperClass = 'form-group';
        $this->multiLabelClass = 'control-label';
        $this->descriptionClass = 'col-sm-offset-2 description';
    }
}
