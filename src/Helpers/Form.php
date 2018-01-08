<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Config\ShopCapabilitiesInterface;

/**
 * Provides basic form handling.
 *
 * Most webshop and CMS software provide their own sort of form API. To be able
 * to generalize or abstract our form handling, this class defines our own
 * minimal form API. This allows us to define our form handing in a cross
 * webshop compatible way. The webshop/CMs specific part should then define a
 * wrapper/mapper to the webshop specific way of form handling.
 *
 * This base Form class defines a way to:
 * - Define the form elements.
 * - Render the form, including their values.
 * - Process a form submission:
 *   - Recognise form submission.
 *   - Perform form (submission) validation.
 *   - Execute a task on valid form submission.
 *   - Show success and/or error messages.
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
 * webshop does not really provide a form object:
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
 * This code is to be used when the CMS or webshop does provide its own form
 * handling and processing.
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
 *
 * </code>
 */
abstract class Form
{
    /** @var string */
    protected $type;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var \Siel\Acumulus\Config\ShopCapabilitiesInterface */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $acumulusConfig;

    /** @var array[] */
    protected $fields;

    /** @var bool */
    protected $formValuesSet;

    /** @var array The values to be placed on the configuration form. */
    protected $formValues;

    /** @var string[] The values as filled in on form submission. */
    protected $submittedValues;

    /** @var string[] Any success messages. */
    protected $successMessages;

    /** @var string[] Any warning messages. */
    protected $warningMessages;

    /** @var string[] Any error messages. */
    protected $errorMessages;

    /**
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Config\ShopCapabilitiesInterface $shopCapabilities
     */
    public function __construct(TranslatorInterface $translator, ConfigInterface $config, ShopCapabilitiesInterface $shopCapabilities)
    {
        $this->successMessages = array();
        $this->warningMessages = array();
        $this->errorMessages = array();
        $this->formValuesSet = false;
        $this->submittedValues = array();

        $this->translator = $translator;
        $this->acumulusConfig = $config;
        $this->shopCapabilities = $shopCapabilities;
        $this->fields = array();

        $class = get_class($this);
        $pos = strrpos($class, '\\');
        $class = $pos !== false ? substr($class, $pos + 1) : $class;
        $classParts = preg_split('/([[:upper:]][[:lower:]]+)/', $class, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
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
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * Returns the success messages.
     *
     * To be used by web shop specific form handling to display success
     * messages.
     *
     * @return string[]
     *   Possibly empty list of success messages, will normally contain 0 or 1
     *   messages
     */
    public function getSuccessMessages()
    {
        return $this->successMessages;
    }

    /**
     * Adds a success message.
     *
     * To be used by web shop specific form handling to add a message to the
     * list of messages to display.
     *
     * @param string $message
     *
     * @return $this
     */
    public function addSuccessMessage($message)
    {
        $this->successMessages[] = $message;
        return $this;
    }

    /**
     * Returns the warning messages.
     *
     * To be used by web shop specific form handling to display validation
     * warnings.
     *
     * @return string[]
     *   An array of translated messages. In case of validation warnings they
     *   are keyed by the name of the form field.
     */
    public function getWarningMessages()
    {
        return $this->warningMessages;
    }

    /**
     * Adds a warning message.
     *
     * To be used by web shop specific form handling to add a message to the
     * list of messages to display.
     *
     * @param string $message
     *
     * @return $this
     */
    public function addWarningMessage($message)
    {
        $this->warningMessages[] = $message;
        return $this;
    }

  /**
     * Returns the error messages.
     *
     * To be used by web shop specific form handling to display validation and
     * connection error messages.
     *
     * An empty result indicates successful validation.
     *
     * @return string[]
     *   An array of translated messages. In case of validation errors they
     *   are keyed by the name of the invalid form field.
     */
    public function getErrorMessages()
    {
        return $this->errorMessages;
    }

  /**
   * Adds an error message.
   *
   * To be used by web shop specific form handling to add a message to the list
   * of messages to display.
   *
   * @param string $message
   *
   * @return $this
   */
    public function addErrorMessage($message)
    {
        $this->errorMessages[] = $message;
        return $this;
    }

    /**
     * Indicates whether the current form handling is a form submission.
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Returns whether the submitted form values are valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errorMessages);
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
            $this->formValues = array_fill_keys($this->getKeys(), '');
            // Merge the default form values into the form values, but do so
            // with some special array handling.
            $defaultFormValues = $this->getDefaultFormValues();
            foreach ($defaultFormValues as $key => $defaultFormValue) {
                // We start with a simple addition or overwrite.
                $this->formValues[$key] = $defaultFormValue;
                // distribute keyed arrays over separate values if existing.
                if (is_array($defaultFormValue)) {
                    foreach ($defaultFormValue as $valueKey => $valueValue) {
                        $fullKey = "{$key}[{$valueKey}]";
                        if (array_key_exists($fullKey, $this->formValues)) {
                            $this->formValues[$fullKey] = $valueValue;
                        }
                    }
                }
            }
            $this->formValues = array_merge($this->formValues, $defaultFormValues);
            if (!empty($this->submittedValues)) {
                $this->formValues = array_merge($this->formValues, $this->submittedValues);
            }
            $this->formValues = array_merge($this->formValues, $this->getFieldValues($this->getFields()));

            $this->formValuesSet = true;
        }
    }

    /**
     * Returns the values for all the fields on the form definition.
     *
     * This method will not have a use on every web shop, but, e.g. Magento and
     * PrestaShop have a separate "bind" method to bind a set of values to a form
     * at once.
     *
     * @return array
     *  An array of values keyed by the form field names.
     */
    public function getFormValues()
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
    protected function getFormValue($name)
    {
        $this->setFormValues();
        return isset($this->formValues[$name]) ? $this->formValues[$name] : '';
    }

    /**
     * Sets the value for a specific form field.
     *
     * @param string $name
     *   The name of the form field.
     * @param mixed $value
     *   The value for the form field.
     */
    protected function setFormValue($name, $value)
    {
        $this->formValues[$name] = $value;
    }

    /**
     * Adds the form values to the field definitions.
     *
     * This method will not have a use on every web shop, but, e.g. VirtueMart and
     * OpenCart have a form helper/renderer to render individual fields including
     * their value attribute instead of binding values to a form and rendering the
     * form.
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
     *
     * @param array[] $fields
     *
     * @return array[]
     */
    protected function addValuesToFields(array $fields)
    {
        foreach ($fields as $name => &$field) {
            if ($field['type'] === 'fieldset') {
                $field['fields'] = $this->addValuesToFields($field['fields']);
            } elseif ($field['type'] === 'checkbox') {
                // Value is a list of checked options.
                $value = array();
                foreach ($field['options'] as $optionName => $optionLabel) {
                    if ($this->getFormValue($optionName)) {
                        $value[] = $optionName;
                    }
                }
                $field['value'] = $value;
            } else {
                // Explicitly set values (in the 'value' key) take precedence over
                // submitted values, which in turn take precedence over default values
                // (gathered via getDefaultFormValues()).
                if (!isset($field['value'])) {
                    $field['value'] = $this->getFormValue($name);
                }
            }
        }
        return $fields;
    }

    /**
     * Returns a set of default values for the form fields.
     *
     * This default implementation returns an empty array, i.e. all form fields
     * are empty, not selected, or unchecked.
     *
     * @return array
     *   An array of default values keyed by the form field names.
     */
    protected function getDefaultFormValues()
    {
        return array();
    }

    /**
     * Returns the set of values directly assigned to the field definitions.
     *
     * These take precedence over default values
     *
     * @param array[] $fields
     *
     * @return array An array of values keyed by the form field names.
     * An array of values keyed by the form field names.
     */
    protected function getFieldValues($fields)
    {
        $result = array();
        foreach ($fields as $id => $field) {
            if (isset($field['value'])) {
                $result[$id] = $field['value'];
            }
            if ($field['type'] === 'fieldset') {
                $result = array_merge($result, $this->getFieldValues($field['fields']));
            }
        }
        return $result;
    }

    /**
     * Extracts the submitted values.
     *
     * Override to restrict the $_POST values to expected values and to do any
     * sanitation.
     */
    protected function setSubmittedValues()
    {
        $this->submittedValues = $this->getPostedValues();
    }

    /**
     * Processes the form.
     *
     * @param bool $executeIfValid
     *   Whether this method should execute the intended action after successful
     *   validation. Some web shops (WooCommerce) sometimes do their own form
     *   handling (setting pages) and we should only do the validation and setting
     *   admin notices as necessary.
     *
     * @return bool
     *   True if there was no form submission or a successful submission.
     */
    public function process($executeIfValid = true)
    {
        $this->formValues = array();
        $this->submittedValues = array();

        // Process the form if it was submitted.
        if ($this->isSubmitted() && $this->systemValidate()) {
            $this->setSubmittedValues();
            $this->validate();
            if ($executeIfValid && $this->isValid()) {
                if ($this->execute()) {
                    $message = $this->t("message_form_{$this->type}_success");
                    if ($message === "message_form_{$this->type}_success") {
                        $message = $this->t('message_form_success');
                    }
                    $this->successMessages[] = $message;
                } else {
                    $message = $this->t("message_form_{$this->type}_error");
                    if ($message === "message_form_{$this->type}_error") {
                        $message = $this->t('message_form_error');
                    }
                    $this->errorMessages[] = $message;
                }
            }
        }

        return $this->isValid();
    }

    /**
     * Checks system specific form properties.
     *
     * This typically includes protection against CSRF attacks and such.
     * Override this per form with the proper checks from the system.
     */
    protected function systemValidate()
    {
        return true;
    }

    /**
     * Performs config form validation.
     *
     * Any errors are stored as a user readable message in the $errorMessages
     * property and will be keyed by the field name.
     *
     * This default implementation does no validation at all. Override to add form
     * specific validation.
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
    abstract protected function execute();

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
     * This is a recursive, keyed array defining each form field. The key defines
     * the name of the form field, to be used for the name, and possibly id,
     * attribute. The values are a keyed array, that can have the following keys:
     * - type: (required, string) fieldset, text, email, password, date, textarea,
     *     select, radio, checkbox, markup.
     * - label: (string) human readable label.
     * - description: (string) human readable help text.
     * - value: (string) the value for the form field.
     * - attributes: (array) keyed array with other - possibly html5 - attributes
     *   to be rendered. Possible keys include:
     *   - size
     *   - class
     *   - required: (bool) whether the field is required.
     * - fields: (array) If the type = 'fieldset', this value defines the fields
     *   (and possibly sub fieldsets) of the fieldset.
     * - options: (array) If the type = checkbox, select or radio, this value
     *   contains the options as a keyed array, the keys being the value to
     *   submit if that choice is selected and the value being the label to show.
     *
     * Do NOT override this method, instead override getFieldDefinitions().
     *
     * @return array[]
     *   The definition of the form.
     */
    public function getFields()
    {
        if (empty($this->fields)) {
            $this->fields = $this->getFieldDefinitions();
        }
        return $this->fields;
    }

    /**
     * Internal version of getFields();
     *
     * Internal method, do not call directly.
     * DO override this method not getFields().
     *
     * @return array[]
     *   The definition of the form.
     */
    abstract protected function getFieldDefinitions();

    /**
     * Returns a list of field ids/keys appearing in the form.
     *
     * @return array
     *   Array of key names appearing in the form, keyed by these names.
     */
    protected function getKeys()
    {
        return $this->getFieldKeys($this->getFields());
    }

    /**
     * Returns the keys of the fields in the given array.
     *
     * Internal method, do not call directly.
     *
     * @param array[] $fields
     *
     * @return array
     *   Array of key names, keyed by these names.
     */
    protected function getFieldKeys(array $fields)
    {
        $result = array();
        foreach ($fields as $key => $field) {
            $result[$key] = $key;
            if ($field['type'] === 'fieldset') {
                $result += $this->getFieldKeys($field['fields']);
            }
        }
        return $result;
    }

    /**
     * Helper method to copy a value from one array to another array.
     *
     * @param array $target
     * @param string $key
     * @param array $source
     * @param string $sourceKey
     *
     * @return bool
     *   True if the value isset and thus has been copied, false otherwise,
     */
    protected function addIfIsset(array &$target, $key, array $source, $sourceKey = '')
    {
        if (empty($sourceKey)) {
            $sourceKey = $key;
        }
        if (isset($source[$sourceKey])) {
            $target[$key] = $source[$sourceKey];
            return true;
        }
        return false;
    }

    /**
     * Indicates whether the given key defines a checkbox field.
     *
     * This base implementation returns false. Override this method if your form
     * has checkbox fields.
     *
     * @param string $key
     *   The name of the field.
     *
     * @return bool
     *   Whether the given key defines a checkbox field.
     */
    protected function isCheckboxKey($key)
    {
        return array_key_exists($key, $this->getCheckboxKeys());
    }

    /**
     * Returns a list of the checkbox names for this form.
     *
     * @return array
     *   An array with as keys the checkbox names of this form and as values the
     *   checkbox collection name the checkbox belongs to.
     */
    protected function getCheckboxKeys()
    {
        // Call getFieldDefinitions(), not getFields(), as that may prevent
        // changes to e.g. fixed values at a later stage in form handling.
        return $this->getCheckboxKeysByFields($this->getFieldDefinitions());
    }

    /**
     * Returns a list of the checkbox names for the given fields.
     *
     * @param array[] $fields
     *   The fields(ets) to extract the checkboxes from.
     *
     * @return array
     *   An array with as keys the checkbox names of this form and as values the
     *   checkbox collection name the checkbox belongs to.
     */
    protected function getCheckboxKeysByFields(array $fields)
    {
        $result = array();
        foreach ($fields as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $result += array_combine(array_keys($field['options']), array_fill(0, count($field['options']), $name));
            } elseif ($field['type'] === 'fieldset') {
                $result += $this->getCheckboxKeysByFields($field['fields']);
            }
        }
        return $result;
    }

    /**
     * Returns a flat array of the posted values.
     *
     * As especially checkbox handling differs per web shop, often resulting in an
     * array of checkbox values, this method returns a flattened version of the
     * posted values.
     *
     * @return array
     */
    protected function getPostedValues()
    {
        $result = $_POST;

        // Handle checkboxes.
        $checkboxKeys = $this->getCheckboxKeys();
        foreach ($checkboxKeys as $checkboxName => $collectionName) {
            if (isset($result[$collectionName]) && is_array($result[$collectionName])) {
                // Checkboxes are handled as an array of checkboxes.
                // Extract the checked values.
                $checkedValues = array_combine(array_values($result[$collectionName]), array_fill(0, count($result[$collectionName]), 1));
                // Replace the array value with the checked values, unset first
                // as the keys for the collection and (1 of the) checkboxes may
                // be the same.
                unset($result[$collectionName]);
                $result += $checkedValues;
            } elseif (isset($result["{$collectionName}_{$checkboxName}"])) {
                // Checkbox keys are prepended with their collection name.
                // Add value for key without collection name.
                $result[$checkboxName] = $result["{$collectionName}_{$checkboxName}"];
                // And unset for key with collection name.
                unset($result["{$collectionName}_{$checkboxName}"]);
            }
        }

        return $result;
    }

    /**
     * Returns the expected date format using a format as accepted by
     * DateTime::createFromFormat().
     *
     * This default implementation assumes that the shop uses the same format
     * options as PHP.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->getShopDateFormat();
    }

    /**
     * Returns the expected date format using a format as accepted by the shop's
     * date handling functions.
     *
     * This default implementation assumes that the shop does not provide its own
     * date handling formats and settings.
     *
     * @return string
     */
    public function getShopDateFormat()
    {
        return 'Y-m-d';
    }
}
