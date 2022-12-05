<?php
namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Version;
use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the Joomla standards.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $majorJoomlaVersion = Version::MAJOR_VERSION;

        $this->elementWrapperClass = 'control-group';
        $this->labelWrapperTag = 'div';
        $this->labelWrapperClass = 'control-label';
        $this->requiredMarkup = '<span class="star" aria-hidden="true"> *</span>';
        $this->inputWrapperTag = 'div';
        $this->inputWrapperClass = 'controls';

        if ($majorJoomlaVersion >= 4) {
            $this->fieldsetWrapperClass = 'options-form';
            $this->fieldsetContentWrapperTag = 'div';
            $this->fieldsetContentWrapperClass = 'form-grid';
            $this->descriptionWrapperClass = ['small', 'text-muted'];
        } else {
            $this->fieldsetWrapperClass = 'adminform';
            $this->multiLabelClass = 'control-label';
            $this->descriptionWrapperClass = ['text-muted', 'controls'];
            $this->markupWrapperClass = 'controls';
        }
    }

    protected function input(array $field): string
    {
        return $field['type'] === 'button' ? $this->button($field) : parent::input($field);
    }

    protected function button(array $field): string
    {
        $output = '';

        $output .= $this->getWrapper('input');
        $attributes = $field['attributes'];
        $attributes = $this->addAttribute($attributes, 'id', $field['id']);
        $attributes = $this->addAttribute($attributes, 'name', $field['name']);
        $output .= $this->getOpenTag('button', $attributes);
        $output .= $field['value'];
        $output .= $this->getCloseTag('button');
        $output .= $this->getWrapperEnd('input');

        return $output;
    }
}
