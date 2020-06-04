<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tag;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Severity;

/**
 * Provides basic config form handling.
 */
abstract class BaseConfigForm extends Form
{
    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\ApiClient\Acumulus $acumulusApiClient
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Acumulus $acumulusApiClient, FormHelper $formHelper, ShopCapabilities $shopCapabilities, Config $config, Translator $translator, Log $log)
    {
        parent::__construct($acumulusApiClient, $formHelper, $shopCapabilities, $config, $translator, $log);
        $this->translator->add(new ConfigFormTranslations());
    }

    /**
     * {@inheritdoc}
     *
     * The results are restricted to the known config keys.
     */
    protected function setSubmittedValues()
    {
        parent::setSubmittedValues();
        $submittedValues = $this->submittedValues;
        $this->submittedValues = [];

        foreach ($this->acumulusConfig->getKeys() as $key) {
            if (!$this->addIfIsset($this->submittedValues, $key, $submittedValues)) {
                // Add unchecked checkboxes and empty arrays, but only if they
                // were defined on the form.
                if ($this->isCheckbox($key)) {
                    $this->submittedValues[$key] = '';
                } elseif ($this->isArray($key)) {
                    $this->submittedValues[$key] = [];
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * This is the set of values as are stored in the config.
     */
    protected function getDefaultFormValues()
    {
        return $this->acumulusConfig->getCredentials()
               + $this->acumulusConfig->getShopSettings()
               + $this->acumulusConfig->getShopEventSettings()
               + $this->acumulusConfig->getCustomerSettings()
               + $this->acumulusConfig->getInvoiceSettings()
               + $this->acumulusConfig->getEmailAsPdfSettings()
               + $this->acumulusConfig->getInvoiceStatusSettings()
               + $this->acumulusConfig->getPluginSettings();
    }

    /**
     * {@inheritdoc}
     *
     * Saves the submitted and validated form values in the configuration store.
     */
    protected function execute()
    {
        $submittedValues = $this->submittedValues;
        return $this->acumulusConfig->save($submittedValues);
    }

    /**
     * Checks the account settings for correctness and sufficient authorization.
     *
     * This is done by calling the about API call and checking the result.
     *
     * @return string
     *   Message to show in the 2nd and 3rd fieldset. Empty if successful.
     */
    protected function checkAccountSettings()
    {
        // Check if we can retrieve a picklist. This indicates if the account
        // settings are correct.
        $message = '';
        if (!$this->emptyCredentials()) {
            $about = $this->acumulusApiClient->getAbout();
            if ($about->hasError()) {
                $message = $about->getByCode(401) ? 'message_error_auth' : ($about->getByCode(403) ? 'message_error_forb' : 'message_error_comm');
                $this->addMessages($about->getMessages(Severity::WarningOrWorse));
            } elseif ($about->getByCode(553)) {
                // Role has been deprecated role for use with the API.
                $this->addMessage($this->t('message_warning_role_deprecated'), Severity::Warning, Tag::UserName);
            } else {
                // Check role for sufficient rights but no overkill.
                $response = $about->getResponse();
                $roleId = (int) $response['roleid'];
                if ($roleId === Api::RoleApiCreator) {
                    $this->addMessage($this->t('message_warning_role_insufficient'), Severity::Warning, Tag::UserName);
                } elseif ($roleId === Api::RoleApiManager) {
                    $this->addMessage($this->t('message_warning_role_overkill'), Severity::Warning, Tag::UserName);
                }
            }
        } else {
            // First fill in your account details.
            $message = 'message_auth_unknown';
        }

        return $message;
    }

    /**
     * Translate and format message.
     *
     * @param $message
     *
     * @return string
     *
     */
    protected function translateAccountMessage($message)
    {
        if (!empty($message)) {
            $formType = $this->isAdvancedConfigForm() ? 'advanced' : 'config';
            $message = sprintf($this->t($message), $this->t("message_error_arg1_$formType"), $this->t("message_error_arg2_$formType"));
        }
        return $message;
    }

    /**
     * Creates a hidden or an option field
     *
     * If there is only 1 option, a hidden value with a fixed value will be
     * created, an option field that gives the user th choice otherwise.
     *
     * @param string $name
     *   The field name.
     * @param string $type
     *   The field type: radio or select.
     * @param bool $required
     *   Whether the required attribute should be rendered.
     * @param array|null $options
     *   An array with value =>label pairs that can be used as an option set.
     *   If null, a similarly as $name named method on $this->shopCapabilities
     *   wil be called to get the options.
     *
     * @return array
     *   A form field definition.
     */
    protected function getOptionsOrHiddenField($name, $type, $required = true, array $options = null)
    {
        if ($options === null) {
            $methodName = 'get' . ucfirst($name) . 'Options';
            $options = $this->shopCapabilities->$methodName();
        }
        if (count($options) === 1) {
            // Make it a hidden field.
            $field = [
                'type' => 'hidden',
                'value' => reset($options),
            ];
        } else {
            $field = [
                'type' => $type,
                'label' => $this->t("field_$name"),
                'description' => $this->t($this->t("desc_$name")),
                'options' => $options,
                'attributes' => [
                    'required' => $required,
                ],
            ];
        }
        return $field;
    }

    /**
     * Returns an option list of all order statuses including an empty choice.
     *
     * @return array
     *   An options array of all order statuses.
     */
    protected function getOrderStatusesList()
    {
        $result = [];

        // Because many users won't know how to deselect a single option in a
        // multiple select element, an empty option is added.
        $result['0'] = $this->t('option_empty_triggerOrderStatus');
        $result += $this->shopCapabilities->getShopOrderStatuses();

        return $result;
    }

    /**
     * Returns whether this form instance is an advanced config form.
     *
     * @return bool
     *   True if this form instance is an advanced config form.
     */
    protected function isAdvancedConfigForm()
    {
        return $this instanceof AdvancedConfigForm;
    }
}
