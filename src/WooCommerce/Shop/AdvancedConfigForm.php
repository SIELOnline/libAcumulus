<?php

namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Provides advanced config form handling.
 *
 * This wooCommerce specific extend overrides:
 * - getPostedValues()
 */
class AdvancedConfigForm extends BaseAdvancedConfigForm {

    /**
     * @inheritDoc
     */
    protected function getPostedValues()
    {
        $result = parent::getPostedValues();
        // WordPress calls wp_magic_quotes() on every request to add magic
        // quotes to form input: we undo this here.
        $result = stripslashes_deep($result);
        return $result;
    }
}
