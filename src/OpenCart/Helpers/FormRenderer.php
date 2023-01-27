<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\FormRenderer as BaseFormRenderer;

/**
 * FormRenderer renders an Acumulus form definition like an OpenCart form.
 */
class FormRenderer extends BaseFormRenderer
{
    public function __construct()
    {
        $this->requiredMarkup = '';
    }

    /**
     * {@inheritdoc}
     */
    protected function renderSimpleField(array $field): string
    {
        $oldElementWrapperClass = $this->elementWrapperClass;
        $this->handleRequired($field);
        $result = parent::renderSimpleField($field);
        $this->elementWrapperClass = $oldElementWrapperClass;
        return $result;
    }

    /**
     * Handles required fields.
     *
     * @param array $field
     */
    protected function handleRequired(array $field): void
    {
        if (!empty($field['attributes']['required'])) {
            if (empty($this->elementWrapperClass)) {
                $this->elementWrapperClass = 'required';
            } else {
                $this->elementWrapperClass = (array) $this->elementWrapperClass;
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                $this->elementWrapperClass[] = 'required';
            }
        }
    }
}
