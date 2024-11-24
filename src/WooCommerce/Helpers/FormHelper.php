<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

/**
 * WooCommerce override of the FormHelper.
 *
 * WordPress calls wp_magic_quotes() on every request to add magic quotes to
 * form input in $_POST: we undo this here.
 */
class FormHelper extends BaseFormHelper
{
    protected function getMeta(): array
    {
        if (empty($this->meta) && $this->isSubmitted() && isset($_POST[static::Meta])) {
            $_POST[static::Meta] = stripslashes($_POST[static::Meta]);
        }
        return parent::getMeta();
    }

    protected function alterPostedValues(array $postedValues): array
    {
        return stripslashes_deep($postedValues);
    }
}
