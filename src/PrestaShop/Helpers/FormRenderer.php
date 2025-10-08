<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like a PrestaShop form.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->fieldsetWrapperTag = 'div';
        $this->fieldsetWrapperClass = 'panel';
        $this->legendWrapperTag = 'div';
        $this->legendWrapperClass = 'panel-heading';

        $this->detailsWrapperTag = 'div';
        $this->detailsWrapperClass = 'panel';
        $this->summaryWrapperTag = 'div';
        $this->summaryWrapperClass = 'panel-heading details';

        $this->fieldsetContentWrapperTag = 'div';
        // The classes below may be specific to the invoice status overview form.
        $this->fieldsetContentWrapperClass = '';

        $this->elementWrapperTag = 'div';
        $this->elementWrapperClass = 'form-group';
        $this->labelClass = 'col-lg-3';
        $this->inputWrapperTag = 'div';
        $this->inputWrapperClass = 'col-lg-9';
        $this->markupWrapperTag = 'div';
        $this->markupWrapperClass = 'col-lg-9';
        $this->descriptionWrapperTag = 'p';
        $this->descriptionWrapperClass = 'help-block';

        $this->requiredMarkup = '';
    }

    /**
     * {@inheritdoc}
     *
     * This override adds the markup to show the icon.
     */
    protected function fieldsetBegin(array $field): string
    {
        if (isset($field['icon'])) {
            $titleTag = $field['type'] === 'fieldset' ? 'legend' : 'summary';
            $field[$titleTag] = '<i class="' . $field['icon'] . '"></i>' . $field[$titleTag];
        }
        return parent::fieldsetBegin($field);
    }
}
