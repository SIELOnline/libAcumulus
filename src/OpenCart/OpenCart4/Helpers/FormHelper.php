<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Opencart\System\Library\Request;
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
     * return \Opencart\System\Library\Request
     */
    private function getRequest(): Request
    {
        return Registry::getInstance()->request;
    }
}
