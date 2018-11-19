<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

/**
 * OpenCart override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return $this->getRequest()->server['REQUEST_METHOD'] == 'POST';
    }

    /**
     * return \Request
     */
    private function getRequest()
    {
        return Registry::getInstance()->request;
    }
}
