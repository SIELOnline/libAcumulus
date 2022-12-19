<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use Siel\Acumulus\Shop\InvoiceStatusForm as BaseInvoiceStatusForm;

/**
 * Provides Joomla specific handling for the Invoice status form.
 */
class InvoiceStatusForm extends BaseInvoiceStatusForm
{
    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions(): array
    {
        $result = parent::getFieldDefinitions();

        // Make it 1 fieldset:
        $allFields = [];
        foreach ($result as $key => $field) {
            if ($field['type'] === 'fieldset' || $field['type'] === 'details') {
                if (isset($field['legend'])) {
                    $allFields[$key] = [
                        'type' => 'markup',
                        'value' => $field['legend'],
                    ];
                }
                foreach ($field['fields'] as $childKey => $childField) {
                    $allFields[$childKey] = $childField;
                }
            } else {
                $allFields[$key] = $field;
            }
        }

        return [
            'acumulus' => [
                'type' => 'fieldset',
                'legend' => 'Acumulus',
                'description' => $this->t('invoice_form_header'),
                'fields' => $allFields,
            ]
        ];
    }
}
