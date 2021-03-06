<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Tag;
use stdClass;

/**
 * Provides basic form helper features.
 *
 * These are features for which the implementation might depend on the hosting
 * web shop software. By extracting these into a separate form helper, the base
 * form class remains shop independent, so that all actual forms (config, batch,
 * ...) can inherit from it.
 */
class FormHelper
{
    /**
     * Name of the hidden meta field.
     */
    const Meta = 'meta';

    /**
     * Name of the hidden meta field.
     */
    const Unique = 'UNIQUE_';

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /**
     * Meta data about the fields on the form.
     *
     * This info is added to the form in a hidden field and thus can come from
     * the posted values (if we are processing a submitted form) or from the
     * defined fields.
     *
     * @var object[]|null
     */
    protected $meta = [];

    /**
     * FormHelper constructor.
     *
     * @param \Siel\Acumulus\Helpers\Translator $translator
     */
    public function __construct(Translator $translator)
    {
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
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * @return object[]|null
     */
    protected function getMeta()
    {
        if (empty($this->meta) && $this->isSubmitted() && isset($_POST[static::Meta])) {
            $meta = json_decode($_POST[static::Meta]);
            if (is_object($meta) || is_array($meta)) {
                $this->setMeta($meta);
            }
        }
        return $this->meta;
    }

    /**
     * @param object|object[]|null $meta
     */
    protected function setMeta($meta)
    {
        // json must change an associative array into an object, we reverse that
        // here.
        if (is_object($meta)) {
            $meta = (array) $meta;
        }
        $this->meta = $meta;
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
     * Returns the keys of the fields in the given array.
     *
     * Internal method, do not call directly.
     *
     * @param array[] $fields
     *
     * @return array
     *   Array of key names, keyed by these names.
     */
    public function addMetaField(array $fields)
    {
        $this->setMeta($this->constructFieldMeta($fields));
        $fields[static::Meta] = [
            'type' => 'hidden',
            'value' => json_encode($this->getMeta()),
        ];
        return $fields;
    }

    /**
     * Returns meta data about the given fields.
     *
     * Internal method, do not call directly.
     *
     * @param array[] $fields
     *
     * @return array
     *   Associative array of field names and their types.
     */
    protected function constructFieldMeta(array $fields)
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $name = isset($field['name']) ? $field['name'] : (isset($field['id']) ? $field['id'] : $key);
            $type = $field['type'];
            if ($type === 'checkbox') {
                foreach ($field['options'] as $checkboxKey => $option) {
                    $data = new stdClass();
                    $data->name = $name;
                    $data->type = $type;
                    $data->collection = $key;
                    $result[$checkboxKey] = $data;
                }
            } else {
                $data = new stdClass();
                $data->name = $name;
                $data->type = $type;
                $result[$key] = $data;
            }

            if (!empty($field['fields'])) {
                $result += $this->constructFieldMeta($field['fields']);
            }
        }
        return $result;
    }

    /**
     * Returns the keys of the fields in the given array.
     *
     * Internal method, do not call directly.
     *
     * @return string[]
     *   Array of key names.
     */
    public function getKeys()
    {
        return array_keys($this->getMeta());
    }

    /**
     * Indicates whether the given key defines a field on the posted form.
     *
     * @param string $key
     *   The name of the field.
     *
     * @return bool
     *   true if the given key defines a field that was rendered on the posted
     *   form, false otherwise.
     */
    public function isKey($key)
    {
        $fieldMeta = $this->getMeta();
        return isset($fieldMeta[$key]);
    }

    /**
     * Indicates whether the given key defines an array field.
     *
     * @param string $key
     *   The name of the field.
     *
     * @return bool
     *   Whether the given key defines an array field.
     */
    public function isArray($key)
    {
        $fieldMeta = $this->getMeta();
        return isset($fieldMeta[$key]) && substr($fieldMeta[$key]->name, -strlen('[]')) === '[]';
    }

    /**
     * Indicates whether the given key defines a checkbox field.
     *
     * @param string $key
     *   The name of the field.
     *
     * @return bool
     *   Whether the given key defines a checkbox field.
     */
    public function isCheckbox($key)
    {
        $fieldMeta = $this->getMeta();
        return isset($fieldMeta[$key]) && $fieldMeta[$key]->type === 'checkbox';
    }

    /**
     * Returns a flat array of the posted values.
     *
     * As especially checkbox handling differs per webshop, often resulting in
     * an array of checkbox values, this method returns a flattened version of
     * the posted values.
     *
     * @return array
     */
    public function getPostedValues()
    {
        $result = $_POST;
        $result = $this->removeUnique($result);
        $result = $this->alterPostedValues($result);
        unset($result[static::Meta]);
        return $result;
    }

    /**
     * If options were made unique (wrt the empty value), remove that here.
     *
     * @param array $postedValues
     *   The set of posted values to alter.
     *
     * @return array
     *   The altered posted values.
     */
    protected function removeUnique(array $postedValues)
    {
        array_walk_recursive($postedValues, function(&$postedValue/*, $key*/) {
            if (in_array(substr($postedValue, 0 , strlen(self::Unique . 'i:')), [self::Unique . 'i:', self::Unique . 's:'])) {
                $postedValue = unserialize(substr($postedValue, strlen(self::Unique)));
            }
        });
        return $postedValues;
    }

    /**
     * Allows to alter the posted values in a web shop specific way.
     *
     * @param array $postedValues
     *   The set of posted values to alter.
     *
     * @return array
     *   The altered posted values.
     */
    protected function alterPostedValues(array $postedValues)
    {
        return $postedValues;
    }

    /**
     * Allows to alter the form values in a web shop specific way.
     *
     * This basic implementation returns the set of form values unaltered.
     *
     * @param array $formValues
     *   The set of form values to alter.
     *
     * @return array
     *   The altered form values.
     */
    public function alterFormValues(array $formValues)
    {
        return $formValues;
    }

    /**
     * Adds a severity css class to form fields that do have a message.
     *
     * @param array[] $fields
     * @param Message[] $messages
     *
     * @return array[]
     */
    public function addSeverityClassToFields(array $fields, array $messages)
    {
        foreach ($messages as $message) {
            if (!empty($message->getField())) {
                $this->addSeverityClassToField($fields, $message->getField(), $this->severityToClass($message->getSeverity()));
            }
        }
        return $fields;
    }

    /**
     * Adds a severity css class to a form field.
     *
     * @param array[] $fields
     * @param string $id
     * @param string $severityClass
     */
    protected function addSeverityClassToField(array &$fields, $id, $severityClass)
    {
        foreach ($fields as $key => &$field) {
            if ($key === $id) {
                if (isset($field['attributes']['class'])) {
                    if (is_array($field['attributes']['class'])) {
                        $field['attributes']['class'][] = $severityClass;
                    } else {
                        $field['attributes']['class'] .= " $severityClass";
                    }
                } else {
                    $field['attributes']['class'] = $severityClass;
                }
            } elseif (!empty($field['fields'])) {
                $this->addSeverityClassToField($field['fields'], $id, $severityClass);
            }
        }
    }

    /**
     * Returns a css class for a given severity.
     *
     * @param int $severity
     *
     * @return string
     */
    protected function severityToClass($severity)
    {
        switch ($severity) {
            case Severity::Exception:
            case Severity::Error:
                return 'error';
            case Severity::Warning:
                return 'warning';
            case Severity::Notice:
                return 'notice';
            case Severity::Info:
                return 'info';
            case Severity::Success:
                return 'success';
            default:
                return '';
        }
    }

    /**
     * Process all fields.
     *
     * @param array $fields
     *
     * @return array[]
     *   The fields with the details processed
     */
    public function processFields(array $fields)
    {
        foreach ($fields as $key => &$field) {
            $field = $this->processField($field, $key);
            // Recursively process children.
            if (isset($field['fields'])) {
                $field['fields'] = $this->processFields($field['fields']);
            }
        }
        return $fields;
    }

    /**
     * (Non recursively) processes 1 field.
     *
     * @param array $field
     * @param string $key
     *
     * @return array
     *   The processed field.
     */
    protected function processField(array $field, $key)
    {
        // Add help text to details fields.
        if ($field['type'] === 'details') {
            if (!empty($field['summary'])) {
                $field['summary'] .= $this->t('click_to_toggle');
            } else {
                $field['summary'] = $this->t('click_to_toggle');
            }
        }
        return $field;
    }
}
