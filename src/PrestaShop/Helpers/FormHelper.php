<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\FormHelper as BaseFormHelper;
use Siel\Acumulus\Tag;
use Tools;

/**
 * PrestaShop override of the FormHelper.
 */
class FormHelper extends BaseFormHelper
{
    /** @var string */
    protected $moduleName = 'acumulus';

    protected $icons = [
        'acumulus' => 'icon-acumulus',
        'accountSettings' => 'icon-user',
        'shopSettings' => 'icon-shopping-cart',
        'triggerSettings' => 'icon-exchange',
        'invoiceSettings' => 'icon-list-alt',
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

    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submitAdd') || Tools::isSubmit('submit' . $this->moduleName) || Tools::getValue('ajax') !== false;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop requires field sets at the top level: move the meta field to
     * the first field collection.
     */
    public function addMetaField(array $fields)
    {
        $fields = parent::addMetaField($fields);
        foreach ($fields as $key => &$field) {
            if (isset($field['fields'])) {
                $field['fields'][static::Meta] = $fields[static::Meta];
                unset($fields[static::Meta]);
                break;
            }
        }
        return $fields;
    }

    /**
     * {@inheritdoc}
     *
     * Prestashop prepends checkboxes with their collection name.
     */
    protected function alterPostedValues(array $postedValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
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
    public function alterFormValues(array $formValues)
    {
        foreach ($this->getMeta() as $key => $fieldMeta) {
            /** @var \stdClass $fieldMeta */
            if ($fieldMeta->type === 'checkbox') {
                if (isset($formValues[$key])) {
                    $prestaShopName = $fieldMeta->collection . '_' . $key;
                    $formValues[$prestaShopName] = $formValues[$key];
                }
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
    public function processField(array $field, $key)
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
