<?php
namespace Siel\Acumulus\Helpers;

/**
 * Provides basic form helper features.
 */
class FormHelper
{
    /**
     * Indicates whether the current form handling is a form submission.
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
