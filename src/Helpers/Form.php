<?php
namespace Siel\Acumulus\Helpers;

use DateTimeImmutable;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Shop\MoreAcumulusTranslations;
use Siel\Acumulus\Tag;

/**
 * Provides basic form handling.
 *
 * Most web shop and CMS software provide their own sort of form API. To be able
 * to generalize or abstract our form handling, this class defines our own
 * minimal form API. This allows us to define our form handing in a cross
 * web shop compatible way. The web shop/CMS specific part should then define a
 * renderer/mapper to the web shop specific way of form handling.
 *
 * This base Form class defines a way to:
 * - Define the form elements.
 * - Render the form, including assigning values to form elements based on
 *   POSTed values, configuration settings, and defaults.
 * - Process a form submission:
 *     * Recognise form submission form just rendering a form.
 *     * Perform form (submission) validation.
 *     * Execute a task on valid form submission.
 *     * Show success and/or error messages.
 *
 * Usage:
 * This code is typically performed by a controller that processes the request:
 * <code>
 *   $form = new ChildForm();
 *   $form->process();
 *   // process any messages ... may also be in the view
 * </code>
 *
 * This code is typically performed by a view and should be used when the CMS or
 * web shop does not really provide a form object:
 * <code>
 *   // Displays the form.
 *   // Create the form and add the values to the elements.
 *   $form = new ChildForm();
 *   $formFields = $form->addValues();
 *   // Render the html for the form.
 *   $formRenderer = new FormRenderer();
 *   $formRenderer->render($form)
 * </code>
 *
 * This code is to be used when the CMS or web shop does provide its own form
 * handling and processing:
 * <code>
 *   // Create shop specific Form object
 *   $shopForm = new ShopForm()
 *   // Map the elements and settings of the Acumulus Form to the shop form.
 *   $formMapper = new FormMapper();
 *   $formMapper->map($shopForm, $form);
 *   // Continue with the shop form by setting the form values ...
 *   $shopForm->setValues($form->GetValues)
 *   // and rendering it.
 *   $shopForm->render()
 * </code>
 */
abstract class Form extends MessageCollection
{
    /**
     * @var string
     *   The type of this form, the class could also be used to determine so,
     *   but as a simple type string is already used on creation, that is used.
     *   Should be one of: register, config, advanced, batch, invoice, rate.
     */
    protected $type;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Helpers\FormHelper */
    protected $formHelper;

    /** @var \Siel\Acumulus\Config\ShopCapabilities */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Config\Config */
    protected $acumulusConfig;

    /** @var \Siel\Acumulus\ApiClient\Acumulus */
    protected $acumulusApiClient;

    /** @var array[] */
    protected $fields;

    /** @var bool */
    protected $formValuesSet;

    /**
     * @var array
     *   The values to be placed on the configuration form.
     */
    protected $formValues;

    /**
     * @var string[]
     *   The values as filled in on form submission.
     */
    protected $submittedValues;

    /**
     * @var bool
     *   For some forms it is important to know which fields were rendered and
     *   of what type they were  and to which set (of checkboxes) they belong.
     *   As empty fields will not be present in the $_POST variable, this may be
     *   hard to determine on submit, so a hidden field containing a summary of
     *   rendered fields is added to the form and will thus be available on
     *   submit.
     *
     *   For other forms it turned out to be a hindrance, so a property now
     *   guides whether the meta field is rendered or not.
     */
    protected $addMeta = true;

    /**
     * @var bool
     *   Whether this form is a full page form, thus surrounded by a <form> tag
     *   and having a possibly standardized submit button.
     *
     *   Not all "forms" as defined by this library are real forms (in the sense
     *   of being enclosed in a <form> tag) or full page forms (having a
     *   "submit" button rendered following the standards of the specific
     *   web shop/CMS).
     */
    protected $isFullPage = true;

    /**
     * @var bool
     *   Whether to add a css class to fields that indicates the severity of
     *   any message linked to this field.
     */
    protected $addSeverityClassToFields = true;

    public function __construct(
        ?Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Translator $translator,
        Log $log
    )
    {
        $this->formValuesSet = false;
        $this->submittedValues = [];

        $this->acumulusApiClient = $acumulusApiClient;
        $this->formHelper = $formHelper;
        $this->shopCapabilities = $shopCapabilities;
        $this->translator = $translator;
        $this->acumulusConfig = $config;
        $this->log = $log;
        $this->fields = [];

        $class = get_class($this);
        $pos = strrpos($class, '\\');
        $class = $pos !== false ? substr($class, $pos + 1) : $class;
        $classParts = preg_split('/([[:upper:]][[:lower:]]+)/', $class, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $this->type = strtolower(is_array($classParts) && !empty($classParts) ? reset($classParts) : $class);
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * Returns the type of the form.
     */
    public function getType(): string
    {
      return $this->type;
    }

    /**
     * returns whether this form is a full page form, thus surrounded by a
     * <form> tag and having a possibly standardized submit button.
     */
    public function isFullPage(): bool
    {
        return $this->isFullPage;
    }

    /**
     * Indicates whether the current form handling is a form submission.
     */
    public function isSubmitted(): bool
    {
        return $this->formHelper->isSubmitted();
    }

    /**
     * Returns whether the submitted form values are valid.
     */
    public function isValid(): bool
    {
        return !$this->hasError();
    }

    /**
     * Sets the form values to use.
     *
     * This is typically the union of the default values, any submitted values,
     * and explicitly set field values.
     */
    protected function setFormValues()
    {
        if (!$this->formValuesSet) {
            // Start by assuring the field definitions are constructed.
            $this->getFields();

            // 1: Hard coded default value for form fields: empty string.
            $this->formValues = array_fill_keys($this->getKeys(), '');

            // 2: Overwrite with the default values from the field definitions,
            // but do so with some special array handling.
            $defaultFormValues = $this->getDefaultFormValues();
            foreach ($defaultFormValues as $key => $defaultFormValue) {
                // We start with a simple overwrite.
                if (array_key_exists($key, $this->formValues)) {
                    $this->formValues[$key] = $defaultFormValue;
                } elseif (is_array($defaultFormValue)) {
                    // Distribute keyed arrays over separate values if existing.
                    foreach ($defaultFormValue as $arrayKey => $arrayValue) {
                        /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
                        $fullKey = "{$key}[{$arrayKey}]";
                        if (array_key_exists($fullKey, $this->formValues)) {
                            $this->formValues[$fullKey] = $arrayValue;
                        }
                    }
                }
            }

            // 3: Overwrite with the submitted values.
            if (!empty($this->submittedValues)) {
                $this->formValues = array_merge($this->formValues, $this->submittedValues);
            }

            // 4: Overwrite with the (hard set) values as set in the field
            // definitions.
            $this->formValues = array_merge($this->formValues, $this->getFieldValues($this->getFields()));

            // 5: Allow for any web shop specific processing of the values.
            // Known usages:
            // - Prepend (checked) checkboxes with their collection name
            //   (PrestaShop).
            // - Place (checked) checkboxes in their collection (Magento).
            $this->formValues = $this->formHelper->alterFormValues($this->formValues);

            $this->formValuesSet = true;
        }
    }

    /**
     * Returns the values for all the fields on the form definition.
     *
     * This method will not have a use on every web shop, but, e.g. Magento and
     * PrestaShop have a separate "bind" method to bind a set of values to a
     * form at once.
     *
     * @return array
     *   An array of values keyed by the form field names.
     */
    public function getFormValues(): array
    {
        $this->setFormValues();
        return $this->formValues;
    }

    /**
     * Returns the value for a specific form field.
     *
     * @param string $name
     *   The name of the form field.
     *
     * @return string
     *   The value for this form field or the empty string if not set.
     */
    protected function getFormValue(string $name): string
    {
        $this->setFormValues();
        return $this->formValues[$name] ?? '';
    }

    /**
     * Sets the value for a specific form field.
     */
    protected function setFormValue(string $name, $value)
    {
        $this->formValues[$name] = $value;
    }

    /**
     * Adds the form values to the field definitions.
     *
     * This method will not have a use on every web shop, but, e.g. VirtueMart
     * and OpenCart have a form helper/renderer to render individual fields
     * including their value attribute instead of binding values to a form and
     * rendering the form.
     */
    public function addValues()
    {
        $this->fields = $this->addValuesToFields($this->getFields());
    }

    /**
     * Adds the form values to the field definitions.
     *
     * This internal version of addValues() passes the fields as a parameter to
     * allow to recursively process field sets.
     */
    protected function addValuesToFields(array $fields): array
    {
        foreach ($fields as $name => &$field) {
            if (!empty($field['fields'])) {
                $field['fields'] = $this->addValuesToFields($field['fields']);
            } elseif ($field['type'] === 'checkbox') {
                // Value is a list of checked options.
                $value = [];
                foreach ($field['options'] as $optionName => $optionLabel) {
                    if ($this->getFormValue($optionName)) {
                        $value[] = $optionName;
                    }
                }
                $field['value'] = $value;
            } elseif (!isset($field['value'])) {
                // Explicitly set values (in the 'value' key) take precedence
                // over submitted values, which in turn take precedence over
                // default values (gathered via getDefaultFormValues()).
                $field['value'] = $this->getFormValue($name);
            }
        }
        return $fields;
    }

    /**
     * Returns a set of default values for the form fields.
     *
     * This default implementation returns an empty array, i.e. all form fields
     * are empty, not selected, or unchecked.
     */
    protected function getDefaultFormValues(): array
    {
        return [];
    }

    /**
     * Returns the set of values directly assigned to the field definitions.
     * These take precedence over default values
     */
    protected function getFieldValues(array $fields): array
    {
        $result = [];
        foreach ($fields as $id => $field) {
            if (isset($field['value'])) {
                $result[$id] = $field['value'];
            }
            if (!empty($field['fields'])) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $result = array_merge($result, $this->getFieldValues($field['fields']));
            }
        }
        return $result;
    }

    /**
     * Extracts the submitted values.
     *
     * Override to restrict the POST values to expected values and to do any
     * sanitation.
     */
    protected function setSubmittedValues()
    {
        $this->submittedValues = $this->formHelper->getPostedValues();
    }

    /**
     * Returns a submitted value.
     *
     * @param string $name
     *   The name of the value to return
     * @param string|null $default
     *   The default to return when this value was not submitted.
     *
     * @return string|null
     *   The submitted value, or the default if the value was not submitted.
     */
    protected function getSubmittedValue(string $name, string $default = null): ?string
    {
        if (empty($this->submittedValues)) {
            $this->setSubmittedValues();
        }
        return array_key_exists($name, $this->submittedValues) ? $this->submittedValues[$name] : $default;
    }

    /**
     * Processes the form.
     *
     * @param bool $executeIfValid
     *   Whether this method should execute the intended action after successful
     *   validation. Some web shops (WooCommerce) sometimes do their own form
     *   handling (setting pages) and we should only do the validation and
     *   setting admin notices as necessary.
     *
     * @return bool
     *   True if there was no form submission or a successful submission.
     */
    public function process(bool $executeIfValid = true): bool
    {
        $this->formValues = [];
        $this->submittedValues = [];

        // Process the form if it was submitted.
        if ($this->isSubmitted()) {
            $this->setSubmittedValues();
            $this->validate();
            if ($executeIfValid && $this->isValid()) {
                if ($this->execute()) {
                    // Add a success message if one was defined for this form.
                    $message = $this->t("message_form_{$this->type}_success");
                    if (!empty($message) && $message !== "message_form_{$this->type}_success") {
                        $this->addMessage($message, Severity::Success);
                    }
                } else {
                    // Add a generic error message if one was defined for this
                    // form. Though note that most forms will add more specific
                    // error messages and thus will not define this one.
                    $message = $this->t("message_form_{$this->type}_error");
                    if (!empty($message) && $message !== "message_form_{$this->type}_error") {
                        $this->addMessage($message, Severity::Error);
                    }
                }
            }
        }

        return $this->isValid();
    }

    /**
     * Performs config form validation.
     *
     * Any errors are stored as a user readable message in the $errorMessages
     * property and will be keyed by the field name.
     *
     * This default implementation does no validation at all. Override to add
     * form specific validation.
     */
    protected function validate()
    {
    }

    /**
     * Executes the form action on valid form submission.
     *
     * Override to implement the actual form handling, like saving values.
     *
     * @return bool
     *   Success.
     */
    abstract protected function execute(): bool;

    /**
     * Returns a definition of the form fields.
     *
     * This should NOT include any:
     * - Submit or cancel buttons. These are often added by the web shop software
     *   in their specific way.
     * - Tokens, form-id's or other (hidden) fields used by the web shop software
     *   to protect against certain attacks or to facilitate internal form
     *   processing.
     *
     * This is a recursive, keyed array defining each form field. The key
     * defines the name of the form field, to be used for the name, and possibly
     * id, attribute. The values are a keyed array, that can have the following
     * keys:
     * - type: (required, string) fieldset, details, text, email, password,
     *   date, textarea, select, radio, checkbox, markup.
     * - legend/summary: (string) human-readable title for a fieldset/details.
     * - label: (string) human-readable label, legend or summary.
     * - description: (string) human-readable help text.
     * - value: (string) the value for the form field.
     * - attributes: (array) keyed array with other, possibly html5, attributes
     *   to be rendered. Possible keys include e.g:
     *     - size
     *     - class
     *     - required: (bool) whether the field is required.
     *     - disabled: (bool) whether the field is disabled.
     * - fields: (array) If the type = 'fieldset' or 'details', this value
     *   defines the (possibly recursive) fields of a fieldset/details element.
     * - options: (array) If the type = checkbox, select or radio, this value
     *   contains the options as a keyed array, the keys being the value to
     *   submit if that choice is selected and the value being the label to
     *   show.
     *
     * Do NOT override this method, instead override getFieldDefinitions().
     *
     * @return array[]
     *   The definition of the form.
     */
    public function getFields(): array
    {
        if (empty($this->fields)) {
            $this->fields = $this->getFieldDefinitions();
            if ($this->addMeta) {
                $this->fields = $this->formHelper->addMetaField($this->fields);
            }
            if ($this->addSeverityClassToFields && $this->hasRealMessages()) {
                $this->fields = $this->formHelper->addSeverityClassToFields($this->fields, $this->getMessages());
            }
            $this->fields = $this->formHelper->processFields($this->fields);
        }
        return $this->fields;
    }

    /**
     * Internal version of getFields();
     *
     * - Internal method, do not call directly but call getFields() instead.
     * - Do override this method, not getFields().
     *
     * @return array[]
     *   The definition of the form.
     */
    abstract protected function getFieldDefinitions(): array;

    /**
     * Returns whether (at least one of) the credentials are (is) empty.
     *
     * @return bool
     *   True, if the credentials are empty, false if they are all filled in.
     */
    protected function emptyCredentials(): bool
    {
        $credentials = $this->acumulusConfig->getCredentials();
        return empty($credentials[Tag::ContractCode]) || empty($credentials[Tag::UserName]) || empty($credentials[Tag::Password]);
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
    protected function getInformationBlock(): array
    {
        $this->loadInfoBlockTranslations();

        $env = $this->acumulusConfig->getEnvironment();
        $module = $this->t('module');
        $environment = [
            'Shop' => "{$env['shopName']} {$env['shopVersion']}",
            "Application" => "Acumulus $module {$env['moduleVersion']}; Library: {$env['libraryVersion']}",
            "System" => "PHP {$env['phpVersion']}; Curl: {$env['curlVersion']}; JSON: {$env['jsonVersion']}; OS: {$env['os']}",
            'Server' => $env['hostName'],
        ];
        $support = strtolower(rtrim($env['shopName'], '0123456789')) . '@acumulus.nl';
        $subject = sprintf($this->t('support_subject'), $env['shopName'], $this->t('module'));
        if ($this->emptyCredentials()) {
            $contractMsg = $this->t('no_contract_data_local') . "\n";
            $contract = [];
            $euCommerceProgressBar = $this->addProgressBar($this->t('unknown'), $this->t('unknown'), 0, 'warning');
            $euCommerceMessage = $this->t('no_contract_data_local');
        } else {
            $myAcumulus = $this->acumulusApiClient->getMyAcumulus();
            $myData = $myAcumulus->getResponse();
            if (!empty($myData)) {
                $contractMsg = '';
                $contract = [
                    $this->t('field_code') => $myData['mycontractcode'] ?? $this->t('unknown'),
                    $this->t('field_companyName') => $myData['mycompanyname'] ?? $this->t('unknown'),
                ];
                if (!empty($myData['mycontractenddate'])) {
                    $endDate = DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $myData['mycontractenddate']);
                    if ($endDate) {
                        $now = new DateTimeImmutable();
                        $days = $now->diff($endDate)->days;
                        if ($days < 40) {
                            $contract[$this->t('contract_end_date')] = $endDate->format('j F Y');
                        }
                    }
                }
                if ($myData['mymaxentries'] != -1) {
                    $contract[$this->t('entries_about')] = sprintf(
                        $this->t('entries_numbers'),
                        $myData['myentries'],
                        $myData['mymaxentries'],
                        $myData['myentriesleft']
                    );
                }
                if ($myData['myemailstatusid'] !== '0') {
                    if ($this->translator->getLanguage() === 'nl' && !empty($myData['myemailstatus_nl'])) {
                        $reason = $myData['myemailstatus_nl'];
                    } elseif ($this->translator->getLanguage() === 'en' && !empty($myData['myemailstatus_en'])) {
                        $reason = $myData['myemailstatus_en'];
                    } elseif (!empty($myData['myemailstatus'])) {
                        $reason = $myData['myemailstatus'];
                    } else {
                        $reason = '';
                    }
                    $contract[$this->t('email_status_label')] = !empty($reason)
                        ? sprintf($this->t('email_status_text_reason'), $reason)
                        : $contract[$this->t('email_status_label')] = $this->t('email_status_text');
                }
            } else {
                $contractMsg = $this->t('no_contract_data') . "\n";
                $contract = $myAcumulus->formatMessages(Message::Format_PlainWithSeverity, Severity::RealMessages);
            }
            $warningPercentage = $this->acumulusConfig->getInvoiceSettings()['euCommerceThresholdPercentage'];
            if ($warningPercentage !== '') {
                $euCommerceReport = $this->acumulusApiClient->reportThresholdEuCommerce();
                if (!$euCommerceReport->hasError()) {
                    $euCommerceReport = $euCommerceReport->getResponse();
                    $reached = $euCommerceReport['reached'] == 1;
                    $nlTaxed = (float) $euCommerceReport['nltaxed'];
                    $threshold = (float) $euCommerceReport['threshold'];
                    $percentage = min($nlTaxed / $threshold * 100.0, 100.0);
                    if ($reached) {
                        $message = $this->t('info_block_eu_commerce_threshold_passed');
                        $status = 'error';
                    } else {
                        if ($percentage < $warningPercentage) {
                            $message = $this->t('info_block_eu_commerce_threshold_ok');
                            $status = 'ok';
                        } else {
                            $message = sprintf($this->t('info_block_eu_commerce_threshold_warning'), $percentage);
                            $status = 'warning';
                        }
                    }
                    $percentage = (int) round($percentage);
                    $euCommerceProgressBar = $this->addProgressBar($nlTaxed, $threshold, $percentage, $status);
                    $euCommerceMessage = $message;
                } else {
                    $euCommerceProgressBar = $this->addProgressBar($this->t('unknown'), $this->t('unknown'), 0, 'error');
                    $euCommerceMessage = $this->t('no_eu_commerce_data') . "\n";
                    $euCommerceMessage .= $this->arrayToList($euCommerceReport->formatMessages(Message::Format_PlainWithSeverity, Severity::RealMessages), true);
                }
            }
        }
        $body = sprintf("%s:\n%s%s%s:\n%s%s\n%s\n%s\n",
            $this->t('contract'),
            $contractMsg,
            $this->arrayToList($contract, false),
            $this->t('environment'),
            $this->arrayToList($environment, false),
            $this->t('support_body'),
            $this->t('regards'),
            $myData['mycontactperson'] ?? $this->t('your_name')
        );
        $moreAcumulus = [
            $this->t('link_login') . '.',
            $this->t('link_app') . '.',
            $this->t('link_manual') . '.',
            $this->t('link_forum') . '.',
            $this->t('link_website') . '.',
            sprintf($this->t('link_support'), rawurldecode($support), rawurlencode($subject), rawurlencode($body)) . '.',
        ];

        $fields = [
            'contractInformation' => [
                'type' => 'markup',
                'value' => '<h3>' . $this->t('contract') . '</h3>' . $contractMsg . $this->arrayToList($contract, true),
            ],
        ];
        if (isset($euCommerceMessage)) {
            /** @noinspection PhpUndefinedVariableInspection */
            $fields['euCommerce'] = [
                'type' => 'markup',
                'value' => '<h3>' . $this->t('euCommerce') . '</h3>' . "<p>$euCommerceProgressBar<br>$euCommerceMessage</p>",
            ];
        }
        $fields += [
            'environmentInformation' => [
                'type' => 'markup',
                'value' => '<h3>' . $this->t('environment') . '</h3>' . $this->arrayToList($environment, true) . $this->t('desc_environmentInformation'),
            ],
            'moreAcumulusInformation' => [
                'type' => 'markup',
                'value' => '<h3>' . $this->t('moreAcumulusTitle') . '</h3>' . $this->arrayToList($moreAcumulus, true),
            ],
        ];

        $wrapperType = $this->getType() === 'batch' ? 'details' : 'fieldset';
        $wrapperTitleType = $this->getType() === 'batch' ? 'summary' : 'legend';
        return [
            'type' => $wrapperType,
            $wrapperTitleType => sprintf($this->t('informationBlockHeader'), $this->t('module')),
            'fields' => $fields,
            'collapsable' => false,
        ];
    }

    protected function addProgressBar($nlTaxed, $threshold, $percentage, $status): string
    {
        return "<span class='acumulus-progressbar'><span class='acumulus-progress acumulus-$status' style='min-width:$percentage%'>$nlTaxed €</span></span><span class='acumulus-threshold'>$threshold €</span>";
    }

    protected function arrayToList(array $list, bool $isHtml): string
    {
        $result = '';
        if (!empty($list)) {
            foreach ($list as $key => $line) {
                if (is_string($key) && !ctype_digit($key)) {
                    $line = "$key: $line";
                }
                $result .= $isHtml ? "<li>$line</li>" : "• $line";
                $result .= "\n";
            }
            if ($isHtml) {
                $result = "<ul>$result</ul>";
            }
            $result .= "\n";
        }
        return $result;
    }

    /**
     * Converts a picklist response into a set of options, e.g. for a dropdown.
     * A picklist is a list of items that have the following structure:
     * - Each picklist item contains an identifying value in the 1st entry.
     * - Most picklist items contain a describing string in the 2nd entry.
     * - Some picklist items contain an alternative/additional description in
     *   the 3rd entry.
     * - The company type picklist contains an english resp Dutch description in
     *   the 2nd and 3rd entry.
     *
     * @param \Siel\Acumulus\ApiClient\Result $picklist
     *   The picklist result structure.
     * @param string|null $emptyValue
     *   The value to use for an empty selection.
     * @param string|null $emptyText
     *   The label to use for an empty selection.
     *
     * @return array
     */
    protected function picklistToOptions(Result $picklist, ?string $emptyValue = null, ?string $emptyText = null): array
    {
        $result = [];

        // Empty value, if any, at top.
        if ($emptyValue !== null) {
            $result[$emptyValue] = $emptyText;
        }

        // Other values follow, we do not change the order.
        $pickListItems = $picklist->getResponse();
        foreach ($pickListItems as $picklistItem) {
            // Option value, may not clash with the "empty value" in a weak
            // comparison (Magento uses in_array with the strict parameter set
            // to false).
            $optionId = reset($picklistItem);
            if (ctype_digit((string) $optionId)) {
                $optionId = (int) $optionId;
            }
            if ($optionId == $emptyValue) {
                if ($optionId === $emptyValue) {
                    $this->log->warning('%s: option "%s" (picklist key: %s) equals empty option "%s"', __METHOD__, $optionId, key($picklistItem), $emptyValue);
                }
                $optionId = FormHelper::Unique . serialize($optionId);
            }

            // Option label
            if (count($picklistItem) === 1) {
                $optionText = $optionId;
            } else {
                $optionText = next($picklistItem);
                $key2 = key($picklistItem);
                if (count($picklistItem) > 2) {
                    $optionalText = next($picklistItem);
                    $key3 = key($picklistItem);
                    if (empty($optionText)) {
                        $optionText = $optionalText;
                    } elseif (!empty($optionalText)) {
                        if ($key3 === $key2 . 'nl') {
                            if ($this->translator->getLanguage() === 'nl') {
                                // English and Dutch descriptions and Dutch is
                                // the active language: use the 3rd text.
                                $optionText = $optionalText;
                            }
                        } else {
                            // Additional description: add it.
                            $optionText .= ' (' . $optionalText . ')';
                        }
                    }
                }
            }
            $result[$optionId] = $optionText;
        }

        return $result;
    }

    /**
     * Returns the html of an <img> tag to show the logo.
     */
    protected function getLogo(int $size = 150): string
    {
        /** @noinspection HtmlUnknownTarget */
        return sprintf('<img src="%1$s" alt="Logo SIEL Acumulus" title="SIEL Acumulus" width="%2$d" height="%2$d">', $this->getLogoUrl(), $size);
    }

    /**
     * Returns the url to the logo.
     *
     * @return string
     */
    protected function getLogoUrl(): string
    {
      return $this->shopCapabilities->getLink('logo');
    }

    /**
     * Returns a list of field ids/keys appearing in the form.
     */
    protected function getKeys(): array
    {
        return $this->formHelper->getKeys();
    }

    /**
     * Indicates whether the given key defines a field on the posted form.
     */
    protected function isKey(string $key): bool
    {
        return $this->formHelper->isKey($key);
    }

    /**
     * Indicates whether the given key defines an array field.
     */
    protected function isArray(string $key): bool
    {
        return $this->formHelper->isArray($key);
    }

    /**
     * Indicates whether the given key defines a checkbox field.
     */
    protected function isCheckbox(string $key): bool
    {
        return $this->formHelper->isCheckbox($key);
    }

    /**
     * Helper method to copy a value from one array to another array.
     *
     * @return bool
     *   True if the value is set in the source array and thus has been copied
     *   to the target array, false otherwise(value not set in thr source array.
     */
    protected function addIfIsset(array &$target, string $key, array $source): bool
    {
        if (isset($source[$key])) {
            $target[$key] = $source[$key];
            return true;
        }
        return false;
    }

    /**
     * Loads the translations for the info block.
     */
    protected function loadInfoBlockTranslations()
    {
        static $translationsAdded = false;
        if (!$translationsAdded) {
            $this->translator->add(new MoreAcumulusTranslations());
            $translationsAdded = true;
        }
    }
}
