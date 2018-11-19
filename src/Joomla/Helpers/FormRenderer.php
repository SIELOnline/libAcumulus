<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JHtml;
use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the Joomla/VirtueMart standards.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
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
        $this->markupWrapperClass = 'controls';
    }

    /**
     * {@inheritdoc}
     */
    public function input($type, $id, $name, $value = '', array $attributes = array())
    {
        $output = '';
        if ($type === 'date') {
            $output .= $this->getWrapper('input');
            $output .= JHtml::calendar($value, $name, $id, '%Y-%m-%d', $attributes);
            $output .= $this->getWrapperEnd('input');
        } else {
            $output .= parent::input($type, $id, $name, $value, $attributes);
        }
        return $output;
    }
}
