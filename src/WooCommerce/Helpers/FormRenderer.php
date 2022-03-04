<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * Class FormRenderer renders a form in the WordPress settings pages standard.
 */
class FormRenderer extends BaseFormRenderer
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->radioWrapperTag = 'fieldset';
        $this->checkboxWrapperTag = 'fieldset';
        $this->fieldsetWrapperTag = '';
        $this->legendWrapperTag = 'h2';
        $this->fieldsetContentWrapperTag = 'table';
        $this->fieldsetContentWrapperClass = 'form-table';
        $this->detailsWrapperClass = 'form-table';
        $this->elementWrapperTag = 'tr';
        $this->labelWrapperTag = 'th';
        $this->labelWrapperClass = 'titledesc';
        $this->inputDescriptionWrapperTag = 'td';
        $this->inputDescriptionWrapperClass = 'forminp';
        $this->fieldsetDescriptionWrapperTag = 'td';

        $this->radioInputInLabel = true;
        $this->checkboxInputInLabel = true;
    }

    /**
     * @inheritDoc
     *
     * This override adds an information icon as label for descriptions at the
     * fieldset level.
     */
    protected function renderDescription($text, $isFieldset = false): string
    {
        $output = '';
        $wrapperType = $isFieldset ? 'fieldsetDescription' : 'description';
        if (!empty($text)) {
            if ($isFieldset) {
                $output .= $this->getWrapper('label', ['class' => 'fieldset-description-label']);
                $output .= '🛈';
                $output .= $this->getWrapperEnd('label');
                $output .= $this->getWrapper($wrapperType);
                $output .= $text;
                $output .= $this->getWrapperEnd($wrapperType);
            } else {
                /** @noinspection NestedPositiveIfStatementsInspection */
                if ($this->usePopupDescription) {
                    $output .= wc_help_tip($text);
                } else {
                    $output .= parent::renderDescription($text, $isFieldset);
                }
            }
        }
        return $output;
    }
}
