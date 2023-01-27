<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;

/**
 * OpenCart override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /**
     * {@inheritdoc}
     *
     * @noinspection PhpMissingParentCallCommonInspection parent is default
     *   fall back.
     */
    public function isSubmitted(): bool
    {
        return $this->getRequest()->server['REQUEST_METHOD'] === 'POST';
    }

    /**
     * return \Opencart\System\Library\Request|\Request
     */
    private function getRequest()
    {
        return Registry::getInstance()->request;
    }
}
