<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Config\ShopCapabilitiesInterface;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Web\Service;

/**
 * Provides basic config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 * - getPostedValues()
 */
abstract class BaseConfigForm extends Form
{
    /** @var \Siel\Acumulus\Config\ShopCapabilitiesInterface */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $acumulusConfig;

    /** @var \Siel\Acumulus\Web\Service*/
    protected $service;

    /**
     * Contact types picklist result, used to test the connection, storing it in
     * this property prevents another webservice call.
     *
     * @var \Siel\Acumulus\Web\Result
     */
    protected $contactTypesResult;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Config\ShopCapabilitiesInterface $shopCapabilities
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Web\Service $service
     */
    public function __construct(TranslatorInterface $translator, ShopCapabilitiesInterface $shopCapabilities, ConfigInterface $config, Service $service)
    {
        parent::__construct($translator);

        $translations = new ConfigFormTranslations();
        $this->translator->add($translations);

        $this->acumulusConfig = $config;
        $this->shopCapabilities = $shopCapabilities;
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     *
     * The results are restricted to the known config keys.
     */
    protected function setSubmittedValues()
    {
        $postedValues = $this->getPostedValues();
        // Check if the full form was displayed or only the account details.
        $fullForm = array_key_exists('salutation', $postedValues);
        foreach ($this->acumulusConfig->getKeys() as $key) {
            if (!$this->addIfIsset($this->submittedValues, $key, $postedValues)) {
                // Add unchecked checkboxes, but only if the full form was
                // displayed as all checkboxes on this form appear in the full
                // form only.
                if ($fullForm && $this->isCheckboxKey($key)) {
                    $this->submittedValues[$key] = '';
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
        $defaultValues = $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getShopSettings() + $this->acumulusConfig->getShopEventSettings() + $this->acumulusConfig->getCustomerSettings() + $this->acumulusConfig->getInvoiceSettings() + $this->acumulusConfig->getEmailAsPdfSettings() + $this->acumulusConfig->getPluginSettings();
        return $defaultValues;
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
     * Checks if the account settings are correct.
     *
     * This is done by trying to download the contact types picklist.
     *
     * @return string
     *   Message to show in the 2nd and 3rd fieldset. Empty if successful.
     */
    protected function checkAccountSettings()
    {
        // Check if we can retrieve a picklist. This indicates if the account
        // settings are correct.
        $message = '';
        $this->contactTypesResult = null;
        $credentials = $this->acumulusConfig->getCredentials();
        if (!empty($credentials['contractcode']) && !empty($credentials['username']) && !empty($credentials['password'])) {
            $this->contactTypesResult = $this->service->getPicklistContactTypes();
            if ($this->contactTypesResult->hasError()) {
                $message = $this->contactTypesResult->hasCode(401) ? 'message_error_auth' : 'message_error_comm';
                $this->errorMessages = array_merge($this->errorMessages, $this->contactTypesResult->getErrors(Result::Format_PlainTextArray));
                $this->warningMessages = array_merge($this->warningMessages, $this->contactTypesResult->getWarnings(Result::Format_PlainTextArray));
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
            }
            else {
                $optionText = next($picklistItem);
                if (count($picklistItem) > 2) {
                    $optionalText = next($picklistItem);
                    if (empty($optionText)) {
                        $optionText = $optionalText;
                    }
                    elseif (!empty($optionalText)) {
                        $optionText .= ' (' . $optionalText . ')';
                    }
                }
            }
            $result[$optionId] = $optionText;
        };

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

        // @todo: moet 0 er wel bij als dit een multiple select is?
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
