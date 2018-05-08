<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

/**
 * WooCommerce override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /**
     * {@inheritdoc}
     */
    public function getPostedValues(array $checkboxKeys)
    {
        $result = parent::getPostedValues($checkboxKeys);
        // WordPress calls wp_magic_quotes() on every request to add magic
        // quotes to form input: we undo this here.
        $result = stripslashes_deep($result);
        return $result;
    }
}
