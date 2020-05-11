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
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Web\Service;
use Siel\Acumulus\Helpers\Severity;

/**
 * Provides basic config form handling.
 */
abstract class BaseConfigForm extends Form
{
    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /**
     * About API call result.
     *
     * @var \Siel\Acumulus\Web\Result
     */
    protected $about;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Web\Service $service
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Service $service, FormHelper $formHelper, ShopCapabilities $shopCapabilities, Config $config, Translator $translator, Log $log)
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);

        $translations = new ConfigFormTranslations();
        $this->translator->add($translations);

        $this->service = $service;
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
        $this->submittedValues = array();

        foreach ($this->acumulusConfig->getKeys() as $key) {
            if (!$this->addIfIsset($this->submittedValues, $key, $submittedValues)) {
                // Add unchecked checkboxes and empty arrays, but only if they
                // were defined on the form.
                if ($this->isCheckbox($key)) {
                    $this->submittedValues[$key] = '';
                } elseif ($this->isArray($key)) {
                    $this->submittedValues[$key] = array();
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
        $credentials = $this->acumulusConfig->getCredentials();
        if (!empty($credentials[Tag::ContractCode]) && !empty($credentials[Tag::UserName]) && !empty($credentials[Tag::Password])) {
            $this->about = $this->service->getAbout();
            if ($this->about->hasError()) {
                $message = $this->about->getByCode(401) ? 'message_error_auth' : ($this->about->getByCode(403) ? 'message_error_forb' : 'message_error_comm');
                $this->addErrorMessages($this->about->getMessages(Severity::ErrorOrWorse));
                $this->addWarningMessages($this->about->getMessages(Severity::Warning));
            } elseif ($this->about->getByCode(553)) {
                // Role has been deprecated role for use with the API.
                $this->addWarningMessages($this->t('message_warning_role_deprecated'));
            } else {
                // Check role for sufficient rights but no overkill.
                $response = $this->about->getResponse();
                $roleId = (int) $response['roleid'];
                if ($roleId === Api::RoleApiCreator) {
                    $this->addWarningMessages($this->t('message_warning_role_insufficient'));
                } elseif ($roleId === Api::RoleApiManager) {
                    $this->addWarningMessages($this->t('message_warning_role_overkill'));
                }
            }
        } else {
            // First fill in your account details.
            $message = 'message_auth_unknown';
        }

        // Translate and format message.
        if (!empty($message)) {
            $formType = $this->isAdvancedConfigForm() ? 'advanced' : 'config';
            $message = sprintf($this->t($message), $this->t("message_error_arg1_$formType"), $this->t("message_error_arg2_$formType"));
        }

        return $message;
    }

    /**
     * Returns version information.
     *
     * The fields returned:
     * - versionInformation
     * - versionInformationDesc
     *
     * @return array[]
     *   The set of version related informational fields.
     */
    protected function getVersionInformation()
    {
        $env = $this->acumulusConfig->getEnvironment();
        return array(
            'versionInformation' => array(
                'type' => 'markup',
                'value' => "<p>Application: Acumulus module {$env['moduleVersion']}; Library: {$env['libraryVersion']}; Shop: {$env['shopName']} {$env['shopVersion']};<br>" .
                    "Environment: PHP {$env['phpVersion']}; Curl: {$env['curlVersion']}; JSON: {$env['jsonVersion']}; OS: {$env['os']}  Server: {$env['hostName']}.</p>",
            ),
            'versionInformationDesc' => array(
                'type' => 'markup',
                'value' => $this->t('desc_versionInformation'),
            ),
        );
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
            $field = array(
                'type' => 'hidden',
                'value' => reset($options),
            );
        } else {
            $field = array(
                'type' => $type,
                'label' => $this->t("field_$name"),
                'description' => $this->t($this->t("desc_$name")),
                'options' => $options,
                'attributes' => array(
                    'required' => $required,
                ),
            );
        }
        return $field;
    }

    /**
     * Converts a picklist response into a set of options, e.g. for a dropdown.
     *
     * A picklist is a list of items that have the following structure:
     * - Each picklist item contains an identifying value in the 1st entry.
     * - Most picklist items contain a describing string in the 2nd entry.
     * - Some picklist items contain an altenative/additional description in the
     *   3rd entry.
     *
     * @param \Siel\Acumulus\Web\Result $picklist
     *   The picklist result structure.
     * @param string|null $emptyValue
     *   The value to use for an empty selection.
     * @param string|null $emptyText
     *   The label to use for an empty selection.
     *
     * @return array
     * @internal param string $key The key in the picklist result structure under which the actual results*   The key in the picklist result structure under which the actual results
     *   can be found.
     */
    protected function picklistToOptions(Result $picklist, $emptyValue = null, $emptyText = null)
    {
        $result = array();

        // Empty value, if any, at top.
        if ($emptyValue !== null) {
            $result[$emptyValue] = $emptyText;
        }

        // Other values follow, we do not change the order.
        $pickListItems = $picklist->getResponse();
        foreach ($pickListItems as $picklistItem) {
            $optionId = reset($picklistItem);
            if (count($picklistItem) === 1) {
                $optionText = $optionId;
            } else {
                $optionText = next($picklistItem);
                if (count($picklistItem) > 2) {
                    $optionalText = next($picklistItem);
                    if (empty($optionText)) {
                        $optionText = $optionalText;
                    } elseif (!empty($optionalText)) {
                        $optionText .= ' (' . $optionalText . ')';
                    }
                }
            }
            $result[$optionId] = $optionText;
        }

        return $result;
    }

    /**
     * Returns an option list of all order statuses including an empty choice.
     *
     * @return array
     *   An options array of all order statuses.
     */
    protected function getOrderStatusesList()
    {
        $result = array();

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
