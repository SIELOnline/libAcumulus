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
        $this->descriptionWrapperClass = ['controls', 'description'];
        $this->markupWrapperClass = 'controls';
    }

    /**
     * {@inheritdoc}
     */
    public function input(array $field)
    {
        $output = '';
        if ($field['type'] === 'date') {
            $output .= $this->getWrapper('input');
            $output .= JHtml::calendar($field['value'], $field['name'], $field['id'], '%Y-%m-%d', $field['attributes']);
            $output .= $this->getWrapperEnd('input');
        } else {
            $output .= parent::input($field);
        }
        return $output;
    }
}
