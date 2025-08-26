<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use RuntimeException;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Shop\AboutForm;

use function array_key_exists;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Provides basic form handling.
 *
 * Most web shop and CMS software provide their own sort of form API. To be able
 * to generalize or abstract our form handling, this class defines our own
 * minimal form API. This allows us to define our form handing in a cross-webshop
 * compatible way. The web shop/CMS-specific part should then define a
 * renderer/mapper to the webshop-specific way of form handling.
 *
 * This base Form class defines a way to:
 * - Define the form elements, see {@see getFields()}.
 * - Render the form, including assigning values to form elements based on
 *   POSTed values, configuration-settings, and defaults.
 * - Process a form submission:
 *     * Recognise form-submission from just rendering a form.
 *     * Perform form (submission) validation.
 *     * {@see execute()} a task on valid form submission.
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
 *   // Render the HTML for the form.
 *   $formRenderer = new FormRenderer();
 *   $formRenderer->render($form)
 * </code>
 *
 * This code is to be used when the CMS or web shop does provide its own form
 * handling and processing:
 * <code>
 *   // Create webshop-specific Form object
 *   $shopForm = new ShopForm()
 *   // Map the elements and settings of the Acumulus Form to the shop form.
 *   $formMapper = new FormMapper();
 *   $formMapper->map($shopForm, $form);
 *   // Continue with the shop form by setting the form values ...
 *   $shopForm->setValues($form->GetValues)
 *   // and rendering it.
 *   $shopForm->render()
 * </code>
 *
 * @noinspection EfferentObjectCouplingInspection
 */
abstract class Form extends MessageCollection
{
    /**
     * @var string
     *   The type of this form, the class could also be used to determine so,
     *   but as a simple type string is already used on creation, that is used.
     *   Should be one of: 'register', 'settings', 'mappings', 'activate', 'batch',
     *   'invoice', 'message', 'rate'.
     */
    protected string $type;
    protected Log $log;
    protected ?AboutForm $aboutForm = null;
    protected FormHelper $formHelper;
    protected CheckAccount $checkAccount;
    protected ShopCapabilities $shopCapabilities;
    protected Config $acumulusConfig;
    protected Environment $environment;
    protected ?Acumulus $acumulusApiClient;
    /** @var array[] The form field definitions */
    protected array $fields;
    protected bool $formValuesSet;
    /**
     * @var (string|int|float|bool|array|null)[]
     *   The values to be placed on the form. The values that come from the submitted
     *   values are cast to their expected types. Thus, in contrast with $submittedValues,
     *   these are not only strings.
     */
    protected array $formValues;
    /**
     * @var string[]
     *   The values as filled in on the form-submission.
     */
    protected array $submittedValues;
    /**
     * @var bool
     *   For some forms it is important to know which fields were rendered and of what
     *   type they were and to which set (of checkboxes) they belong. As empty fields will
     *   not be present in the $_POST variable, this may be hard to determine on form
     *   submission. Therefore, we add a hidden field containing a summary of the rendered
     *   fields to the form so that this information will be available on from submission.
     *
     *   For other forms it turned out to be a hindrance, so this property now guides
     *   whether the meta-field is rendered or not.
     */
    protected bool $addMeta = true;
    /**
     * @var bool
     *   Whether this form is a full page form, thus surrounded by a <form> tag
     *   and having a possibly standardized 'submit' button.
     *
     *   Not all "forms" as defined by this library are real forms (in the sense
     *   of being enclosed in a <form> tag) or full page forms (having a
     *   "submit" button rendered following the standards of the specific
     *   web shop/CMS).
     */
    protected bool $isFullPage = true;
    /**
     * @var bool
     *   Whether to add a CSS class to fields that indicates the severity of
     *   any message linked to this field.
     */
    protected bool $addSeverityClassToFields = true;

    public function __construct(
        ?Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        CheckAccount $checkAccount,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($translator);
        $this->formValuesSet = false;
        $this->submittedValues = [];

        $this->acumulusApiClient = $acumulusApiClient;
        $this->formHelper = $formHelper;
        $this->checkAccount = $checkAccount;
        $this->shopCapabilities = $shopCapabilities;
        $this->acumulusConfig = $config;
        $this->environment = $environment;
        $this->log = $log;
        $this->fields = [];

        $class = get_class($this);
        $pos = strrpos($class, '\\');
        $class = $pos !== false ? substr($class, $pos + 1) : $class;
        $classParts = preg_split('/([[:upper:]][[:lower:]]+)/', $class, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $this->type = strtolower(is_array($classParts) && count($classParts) !== 0 ? reset($classParts) : $class);
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
     * <form> tag and having a possibly standardized 'submit' button.
     *
     * @noinspection PhpUnused
     *   Used in shop webshop specific projects.
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
    protected function setFormValues(): void
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
                // We start by simply overwriting.
                if (array_key_exists($key, $this->formValues)) {
                    $this->formValues[$key] = $defaultFormValue;
                } elseif (is_array($defaultFormValue)) {
                    // Distribute keyed arrays over separate values if existing.
                    foreach ($defaultFormValue as $arrayKey => $arrayValue) {
                        $fullKey = "{$key}[$arrayKey]";
                        if (array_key_exists($fullKey, $this->formValues)) {
                            $this->formValues[$fullKey] = $arrayValue;
                        }
                    }
                }
            }

            // 3: Overwrite with the submitted values.
            if (count($this->submittedValues) !== 0) {
                $this->formValues = array_merge($this->formValues, $this->submittedValues);
            }

            // 4: Overwrite with the (hard set) values as set in the field
            // definitions.
            $this->formValues = array_merge($this->formValues, $this->getFieldValues($this->getFields()));

            // 5: Allow for any webshop-specific processing of the values.
            // Known usages:
            // - Prepend (checked) checkboxes with their collection name
            //   (PrestaShop).
            // - Place (checked) checkboxes in their collection (Magento).
            // - Make "empty" values non-empty (Magento).
            // - Flatten array values: $this->formValues['Customer[email]'] =>
            //   $this->formValues['Customer_email'] (Magento).
            $this->formValues = $this->formHelper->alterFormValues($this->formValues, $this->getFields());

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
     * @return string|int|float|bool|array|null
     *   The value for this form field or the empty string if not set.
     */
    protected function getFormValue(string $name): float|int|bool|array|string|null
    {
        $this->setFormValues();
        return $this->formValues[$name] ?? '';
    }

    /**
     * Sets the value for a specific form field.
     */
    protected function setFormValue(string $name, $value): void
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
    public function addValues(): void
    {
        $this->fields = $this->addValuesToFields($this->getFields());
    }

    /**
     * Adds the form values to the field definitions.
     *
     * This internal version of addValues() passes the fields as a parameter to
     * allow recursively processing field sets.
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
    protected function setSubmittedValues(): void
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
    protected function getSubmittedValue(string $name, ?string $default = null): ?string
    {
        if (count($this->submittedValues) === 0) {
            $this->setSubmittedValues();
        }
        return array_key_exists($name, $this->submittedValues) ? $this->submittedValues[$name] : $default;
    }

    /**
     * Processes the form.
     *
     * @param bool $executeIfValid
     *   Whether this method should execute the intended action after successful
     *   validation. Some webshops do their own form handling (e.g. WordPress setting
     *   pages), and we should only do the validation and setting admin notices as necessary.
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
                        $this->createAndAddMessage($message, Severity::Success);
                    }
                } else {
                    // Add a generic error message if one was defined for this
                    // form. Though note that most forms will add more specific
                    // error messages and thus will not define this one.
                    $message = $this->t("message_form_{$this->type}_error");
                    if (!empty($message) && $message !== "message_form_{$this->type}_error") {
                        $this->createAndAddMessage($message, Severity::Error);
                    }
                }
            }
        }

        return $this->isValid();
    }

    /**
     * Performs form validation.
     *
     * Any errors are stored as a user-readable message in the $errorMessages
     * property and will be keyed by the field name.
     *
     * This default implementation does no validation at all. Override to add
     * form-specific validation.
     */
    protected function validate(): void
    {
    }

    /**
     * Executes the form action on a valid form submission.
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
     * - Tokens, form-ids, or other (hidden) fields used by the web shop software
     *   to protect against certain attacks or to facilitate internal form
     *   processing.
     *
     * This is a recursive, keyed array defining each form field. The key
     * defines the name of the form field, to be used for the name and id
     * attribute. The values are a keyed array that can have the following
     * keys:
     * - type: (required, string): 'fieldset', 'details', 'text', 'number', 'password',
     *   'email', 'date', 'textarea', 'select', 'radio', 'checkbox', 'markup', 'hidden',
     *   or 'collection'.
     * - legend/summary: (string) human-readable title for a fieldset/details.
     * - label: (string) human-readable 'label', 'legend', or 'summary'.
     * - description: (string) human-readable help text.
     * - value: (string) the value for the form field. Will normally be set by
     *   the form processing, the developer of a form should override
     *   {@see getDefaultFormValues()}. The exceptions are markup and,
     *   probably, hidden fields.
     * - attributes: (array) keyed array with other, possibly html5, attributes
     *   to be rendered. Possible keys include e.g:
     *     - size
     *     - class
     *     - required: (bool) whether the field is required.
     *     - disabled: (bool) whether the field is disabled.
     * - fields: (array) If type = 'fieldset' or 'details', this value defines
     *   the (possibly recursive) fields of a fieldset/details element.
     * - options: (array) If the type = checkbox, select or radio, this value
     *   contains the options as a keyed array, the keys being the value to
     *   submit if that choice is selected and the value being the label to
     *   show.
     *
     * Do NOT override this method, instead override {@see getFieldDefinitions()}.
     *
     * @return array[]
     *   The definition of the form.
     */
    public function getFields(): array
    {
        if (count($this->fields) === 0) {
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
     * Internal version of {@see getFields()}.
     *
     * - Internal method, do not call directly but call {@see getFields()}
     *   instead.
     * - Do override this method, not {@see getFields()}.
     *
     * @return array[]
     *   The definition of the form.
     */
    abstract protected function getFieldDefinitions(): array;

    /**
     * Adds a form message.
     *
     * @param string $field
     *   The id of the form field. Does not have to be specified if multiple
     *   fields are involved
     *
     * @return $this
     */
    public function addFormMessage(string $message, int $severity, string $field = ''): MessageCollection
    {
        return $this->addMessage(Message::createForFormField($message, $severity, $field));
    }

    /**
     * Returns the Account fields status
     *
     * @return null|bool|string
     *   - null: (some) credentials are empty
     *   - true: credentials are correct
     *   - false: credentials are incorrect and $returnMessage = false
     *   - string: credentials are incorrect and $returnMessage = true
     *
     *  @ntodo  $accountStatus is being too complex (null|bool|string) => refactor into a
     *    small object/array
     */
    protected function getAccountStatus(bool $returnMessage = false): null|bool|string
    {
        return $this->checkAccount->getAccountStatus($returnMessage);
    }

    /**
     * Checks if a string is a valid (list of) e-mail address(es).
     *
     * @param string $submittedValue
     *   The value to check. The empty string is not considered a valid e-mail address.
     * @param bool $multi
     *   Whether to allow multiple e-mail addresses separated by a ',' or ';'.
     */
    public function isEmailAddress(string $submittedValue, bool $multi = false): bool
    {
        $regexpEmail = '[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+';
        $regexpSingle = '/^' . $regexpEmail . '$/';
        $regexpMulti = '/^' . $regexpEmail . '([,;]' . $regexpEmail . ')*$/';
        $regex = $multi ? $regexpMulti : $regexpSingle;
        return (bool) preg_match($regex, $submittedValue);
    }

    /**
     * Returns an option list of all order statuses including an empty choice.
     *
     * @return array
     *   An array of all order statuses that can be used for the 'options' of a 'select'.
     */
    protected function getOrderStatusesList(string $emptyOption): array
    {
        $result = [];

        // Because many users won't know how to deselect a single option in a
        // multiple select element, an empty option is added.
        $result['0'] = $this->t($emptyOption);
        $result += $this->shopCapabilities->getShopOrderStatuses();

        return $result;
    }

    /**
     * Converts a picklist response into a set of options, e.g. for a dropdown.
     * A picklist is a list of items that have the following structure:
     * - Each picklist item contains an identifying value in the 1st entry.
     * - Most picklist items contain a describing string in the 2nd entry.
     * - Some picklist items contain an alternative/additional description in
     *   the 3rd entry.
     * - The company type picklist contains an English resp. Dutch description in
     *   the 2nd and 3rd entry.
     *
     * @param \Siel\Acumulus\ApiClient\AcumulusResult $picklist
     *   The picklist result structure.
     * @param int|string|null $emptyValue
     *   The value to use for an empty selection.
     * @param string|null $emptyText
     *   The label to use for an empty selection.
     */
    protected function picklistToOptions(AcumulusResult $picklist, int|string|null $emptyValue = null, ?string $emptyText = null): array
    {
        $result = [];

        // Empty value, if any, at the top.
        if ($emptyValue !== null) {
            $result[$emptyValue] = $emptyText;
        }

        // Other values follow, we do not change the order.
        $pickListItems = $picklist->getMainAcumulusResponse();
        foreach ($pickListItems as $picklistItem) {
            // Prefer integer ids.
            $optionId = reset($picklistItem);
            if (is_string($optionId) && ctype_digit($optionId)) {
                $optionId = (int) $optionId;
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
                                // English and Dutch descriptions, and Dutch is
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
     * Returns the HTML of an <img> tag to show the logo.
     */
    protected function getLogo(int $size = 150): string
    {
        /** @noinspection HtmlUnknownTarget */
        return sprintf(
            '<img src="%1$s" alt="Logo SIEL Acumulus" title="SIEL Acumulus" width="%2$d" height="%2$d">',
            $this->getLogoUrl(),
            $size
        );
    }

    /**
     * Returns the url to the logo.
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
     *   to the target array, false otherwise (value not set in the source
     *   array).
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
     * Returns the About block to add to this form.
     */
    protected function getAboutBlock(null|bool|string $accountStatus): array
    {
        if ($this->aboutForm === null) {
            throw new RuntimeException('About block not available');
        }

        $wrapperType = match ($this->getType()) {
            'register', 'activate', 'batch' => 'details',
            'settings', 'mappings' => in_array($accountStatus, [null, false], true) ? 'details' : 'fieldset',
            default => throw new RuntimeException('Unexpected form type ' . $this->getType()),
        };
        return $this->aboutForm->getAboutBlock($accountStatus, $wrapperType);
    }
}
