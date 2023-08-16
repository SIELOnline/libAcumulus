<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;
use Siel\Acumulus\Tag;
use Tools;

/**
 * PrestaShop override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    protected string$moduleName = 'acumulus';
    protected array $icons = [
        'accountSettings' => 'icon-user',
        'shopSettings' => 'icon-shopping-cart',
        'triggerSettings' => 'icon-exchange',
        'invoiceSettings' => 'icon-list-alt',
        'invoiceLinesSettingsHeader' => 'icon-list-alt',
        'paymentMethodAccountNumberFieldset' => 'icon-credit-card',
        'paymentMethodCostCenterFieldset' => 'icon-credit-card',
        'emailAsPdfSettingsHeader' => 'icon-file-pdf-o',
        'pluginSettings' => 'icon-puzzle-piece',
        'versionInformation' => 'icon-info-circle',
        'advancedConfig' => 'icon-cogs',
        'configHeader' => 'icon-cogs',
        'tokenHelpHeader' => 'icon-question-circle',
        'relationSettingsHeader' => 'icon-users',
        'optionsSettingsHeader' => 'icon-indent',
        'batchFields' => 'icon-exchange',
        'batchLog' => 'icon-list',
        'batchInfo' => 'icon-info-circle',
        'congratulations' => 'icon-thumbs-up',
        'loginDetails' => 'icon-key',
        'apiLoginDetails' => 'icon-key',
        'whatsNext' => 'icon-forward',
    ];

    public function isSubmitted(): bool
    {
        return Tools::isSubmit('submitAdd') || Tools::isSubmit('submit' . $this->moduleName) || Tools::getValue('ajax') !== false;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop prepends checkboxes with their collection name.
     */
    protected function alterPostedValues(array $postedValues): array
    {
        /** @var object[]|null $meta */
        $meta = $this->getMeta();
        foreach ($meta as $key => $fieldMeta) {
            if ($fieldMeta->type === 'checkbox') {
                $prestaShopName = $fieldMeta->collection . '_' . $key;
                if (isset($postedValues[$prestaShopName])) {
                    $postedValues[$key] = $postedValues[$prestaShopName];
                    unset($postedValues[$prestaShopName]);
                }
            }
        }
        return $postedValues;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop prepends checkboxes with their collection name.
     */
    public function alterFormValues(array $formValues, array $fields): array
    {
        $formValues = parent::alterFormValues($formValues,$fields);
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if (($fieldMeta->type === 'checkbox') && isset($formValues[$key])) {
                $prestaShopName = $fieldMeta->collection . '_' . $key;
                $formValues[$prestaShopName] = $formValues[$key];
            }
        }
        return $formValues;
    }

    /**
     * {@inheritdoc}
     *
     * This override adds a "details" class to all details fields, thereby
     * allowing a js solution.
     */
    protected function processField(array $field, string $key): array
    {
        $field = parent::processField($field, $key);

        // Password fields are rendered (and may remain) empty to indicate no
        // change.
        if ($key === Tag::Password) {
            $field['attributes']['required'] = false;
        }

        // Add icon to headers.
        if (isset($this->icons[$key])) {
            $field['icon'] = $this->icons[$key];
        }

        // Add class "details" to icon (part of headers).
        if ($field['type'] === 'details') {
            if (empty($field['icon'])) {
                $field['icon'] = 'details';
            } else {
                $field['icon'] .= ' details';
            }
        }

        return $field;
    }
}
