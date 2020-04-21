<?php
namespace Siel\Acumulus\Helpers;

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
     * Meta data about the fields on the form.
     *
     * This info is added to the form in a hidden field and thus can come from
     * the posted values (if we are processing a submitted form) or from the
     * defined fields.
     *
     * @var object[]|null
     */
    protected $meta = array();

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
        $fields[static::Meta] = array(
            'type' => 'hidden',
            'value' => json_encode($this->getMeta()),
        );
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
        $result = array();
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
        $result = $this->alterPostedValues($result);
        unset($result[static::Meta]);
        return $result;
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
}
