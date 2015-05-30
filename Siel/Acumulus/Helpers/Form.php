<?php
namespace Siel\Acumulus\Helpers;

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
 * webshop does not really provide a form rendering engine:
 * <code>
 *   // Displays the form.
 *   // Get the element definitions: a recursive array.
 *   $formFields = $form->getFields();
 *   // Add the values to the elements.
 *   $formFields = $form->addValues($formFields);
 *   // Render the html for the form.
 *   $formRenderer = new FormRenderer();
 *   $formRenderer->fields($formFields))
 * </code>
 *
 * This code is to be used when the CMS or webshop does provide its own form
 * handling and processing.
 */
abstract class Form {

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var bool */
  protected $formValuesSet;

  /** @var array The values to be placed on the configuration form. */
  protected $formValues;

  /** @var string[] The values as filled in on form submission. */
  protected $submittedValues;

  /** @var string[] Any success messages. */
  protected $successMessages;

  /** @var string[] Any error messages. */
  protected $errorMessages;

  /**
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   */
  public function __construct(TranslatorInterface $translator) {
    $this->successMessages = array();
    $this->errorMessages = array();
    $this->formValuesSet = false;

    $this->translator = $translator;
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
  protected function t($key) {
    return $this->translator->get($key);
  }

  /**
   * To be used by web shop specific form handling to display a success message.
   *
   * @return string[]
   */
  public function getSuccessMessages() {
    return $this->successMessages;
  }

  /**
   * To be used by web shop specific form handling to display validation and
   * connection error messages.
   *
   * An empty result indicates successful validation.
   *
   * @return string[]
   *   An array of translated messages. In case of validation errors they are
   *   keyed by the name of the invalid form field.
   */
  public function getErrorMessages() {
    return $this->errorMessages;
  }

  /**
   * Indicates whether the current form handling is a form submission.
   *
   * @return bool
   */
  protected function isSubmitted() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
  }

  /**
   * Returns whether the submitted form values are valid.
   *
   * @return bool
   */
  protected function isValid() {
    return empty($this->errorMessages);
  }

  /**
   * Sets the form values to use.
   *
   * This is typically the union of the default values and any submitted values.
   */
  protected function setFormValues() {
    if (!$this->formValuesSet) {
      $this->formValues = $this->getDefaultFormValues();
      if (!empty($this->submittedValues)) {
        $this->formValues = array_merge($this->formValues, $this->submittedValues);
      }

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
  public function getFormValues() {
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
  protected function getFormValue($name) {
    $this->setFormValues();
    return isset($this->formValues[$name]) ? $this->formValues[$name] : '';
  }

  /**
   * Adds the form values to the field definitions.
   *
   * This method will not have a use on every web shop, but, e.g. VirtueMart and
   * OpenCart have a form helper/renderer to render individual fields including
   * their value attribute instead of binding values ot a form and rendering the
   * form.
   *
   * @param array[] $fields
   *
   * @return array[]
   */
  public function addValues(array $fields) {
    foreach ($fields as $name => &$field) {
      if ($field['type'] === 'fieldset') {
        $field['fields'] = $this->addValues($field['fields']);
      }
      else if ($field['type'] === 'checkbox') {
        // Value is a list of checked options.
        $value = array();
        foreach ($field['options'] as $optionName => $optionLabel) {
          if ($this->getFormValue($optionName)) {
            $value[] = $optionName;
          }
        }
        $field['value'] = $value;
      }
      else {
        // Explicitly set values (in the 'value' key) take precedence over
        // submitted values, who in turn take precedence over default values
        // (gathered via geDefaultFormValues()).
        if (!isset($field['value'])) {
          $field['value'] = $this->getFormValue($name);
        }
      }
    }
    return $fields;
  }

  /**
   * Returns a set of default values for the fom fields.
   *
   * This default implementation returns an empty array, i.e. all form fields
   * are empty, not selected, or unchecked.
   *
   * @return array
   *   An array of default values keyed by the form field names.
   */
  protected function getDefaultFormValues() {
    return array();
  }

  /**
   * Extracts the submitted values.
   *
   * Override to restrict the $_POST values to expected values and to do any
   * sanitation.
   */
  protected function setSubmittedValues() {
    $this->submittedValues = $_POST;
  }

  /**
   * Processes the form.
   *
   * @return bool
   *   True if there was no form submission or a successful submission.
   */
  public function process() {
    $this->formValues = array();
    $this->submittedValues = array();

    // Process the form if it was submitted.
    if ($this->isSubmitted() && $this->systemValidate()) {
      $this->setSubmittedValues();
      $this->validate();
      if ($this->isValid()) {
        if ($this->execute()) {
          $message = $this->t('message_form_success');
          if ($message !== 'message_form_success') {
            $this->successMessages[] = $message;
          }
        }
        else {
          $message = $this->t('message_form_error');
          if ($message !== 'message_form_error') {
            $this->errorMessages[] = $message;
          }
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
  protected function systemValidate() {
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
  protected function validate() {
    if (method_exists($this, 'systemValidate')) {
      $this->systemValidate();
    }
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
   * - type: (required, string) fieldset, text, password, markup, etc.
   * - label: (string) human readable label.
   * - description: (string) human readable help text.
   * - value: (string) the value for the form field.
   * - required: (bool, defaults to false) whether the field is required.
   * - attributes: (array) keyed array with other attributes to be rendered.
   *   Possible keys include:
   *   - size
   *   - class
   * - fields: (array) If the type = 'fieldset', this value defines the fields
   *   (and possibly sub fieldsets) of the fieldset.
   * - options: (array) If the type = checkbox, select or radio, this value
   *   contains the options as a keyed array, the keys being the value to
   *   submit if that choice is selected and the value being the label to show.
   *
   * @return array[]
   *   The definition of the form.
   */
  abstract public function getFields();

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
  protected function addIfIsset(array &$target, $key, array $source, $sourceKey = '') {
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
  protected function isCheckboxKey($key) {
    return array_key_exists($key, $this->getCheckboxKeys());
  }

  /**
   * Returns a list of the checkbox names for this form.
   *
   * This base implementation returns an empty array. Override this method if
   * your form has checkbox fields.
   *
   * @return array
   *   An array with as keys the checkbox names of this form and as values the
   *   checkbox collection name the checkbox belongs to.
   */
  protected function getCheckboxKeys() {
    return array();
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
  protected function getPostedValues() {
    $result = $_POST;

    foreach ($this->getCheckboxKeys() as $checkboxName => $collectionName) {
      if (isset($result[$collectionName]) && is_array($result[$collectionName])) {
        // Extract the checked values.
        $checkedValues = array_combine(array_values($result[$collectionName]), array_fill(0, count($result[$collectionName]), 1));
        // Replace the array value with the checked values, unset first as the
        // keys for the collection and (1 of the) checkboxes may be the same.
        unset($result[$collectionName]);
        $result += $checkedValues;
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
  public function getDateFormat() {
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
  public function getShopDateFormat() {
    return 'Y-m-d';
  }

}
