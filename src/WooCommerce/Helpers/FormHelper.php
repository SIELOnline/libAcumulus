<?php
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
    /**
     * {@inheritdoc}
     */
    protected function getMeta(): ?array
    {
        if (empty($this->meta) && $this->isSubmitted() && isset($_POST[static::Meta])) {
            $this->setMeta(json_decode(stripslashes($_POST[static::Meta])));
        }
        return $this->meta;
    }

    /**
     * {@inheritdoc}
     */
    protected function alterPostedValues(array $postedValues): array
    {
        return stripslashes_deep($postedValues);
    }
}
