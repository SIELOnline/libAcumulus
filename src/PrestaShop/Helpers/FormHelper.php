<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;
use Tools;

/**
 * PrestaShop override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /** @var string */
    protected $moduleName = 'acumulus';

    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submitAdd') || Tools::isSubmit('submit' . $this->moduleName);
    }
}
