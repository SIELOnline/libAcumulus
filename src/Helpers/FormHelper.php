<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Meta;
use stdClass;

use function is_array;
use function is_object;

/**
 * Provides basic form helper features.
 *
 * These are features for which the implementation might depend on the hosting
 * web shop software. By extracting these into a separate form helper, the base
 * form class remains shop independent, so that all actual forms (config, batch,
 * ...) can inherit from it.
 *
 * ### Note to developers
 * You probably want to override:
 * - {@see severityToCssClass()
 *
 * But may want to override some other as well, a.o:
 * - {@see isSubmitted()
 */
class FormHelper
{
    /**
     * Name of the hidden meta-field.
     */
    public const Meta = 'meta';

    protected Translator $translator;
    protected Log $log;
    /**
     * @var object[]|null
     *   Metadata about the fields on the form.
     *
     *   This info is added to forms having the property {@see Form::$addMeta} set
     *   to true. The info is stored in a hidden field and thus comes from the
     *   posted values if we are processing a submitted form, otherwise it is
     *   constructed from the defined fields.
     */
    protected ?array $meta;

    public function __construct(Translator $translator, Log $log)
    {
        $this->translator = $translator;
        $this->log = $log;
        $this->meta = null;
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
     * @return object[]
     */
    protected function getMeta(): array
    {
        if (empty($this->meta) && $this->isSubmitted() && isset($_POST[static::Meta])) {
            /** @noinspection JsonEncodingApiUsageInspection  Fail silently: tampering  */
            $meta = json_decode($_POST[static::Meta], false);
            if (is_object($meta) || is_array($meta)) {
                $this->setMeta($meta);
            }
        }
        return $this->meta ?? [];
    }

    /**
     * @param object|object[]|null $meta
     */
    protected function setMeta(object|array|null $meta): void
    {
        // JSON must change an associative array into an object, we reverse that
        // here.
        if (is_object($meta)) {
            $meta = (array) $meta;
        }
        $this->meta = $meta;
    }

    /**
     * Indicates whether the current form handling is a form submission.
     */
    public function isSubmitted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Adds the meta-field to the form fields.
     *
     * To prevent problems with rendering (CSS using + or ~ selector) or with
     * PrestaShop that only allows <fieldset>s at the top, the meta-field is added
     * to the first fieldset or details element, or placed at the end.
     *
     * @param array[] $fields
     *
     * @return array[]
     *   $fields with the meta-field added to it.
     */
    public function addMetaField(array $fields): array
    {
        $this->setMeta($this->constructFieldMeta($fields));
        $metaField = [
            'type' => 'hidden',
            'value' => json_encode($this->getMeta(), Meta::JsonFlags),
        ];
        foreach ($fields as &$field) {
            if (isset($field['fields'])) {
                $field['fields'][static::Meta] = $metaField;
                $metaField = null;
                break;
            }
        }
        if ($metaField !== null) {
            $fields[static::Meta] = $metaField;
        }
        return $fields;
    }

    /**
     * Returns metadata about the given fields.
     *
     * Internal method, do not call directly.
     *
     * @param array[] $fields
     *
     * @return array
     *   Associative array of field names and their types.
     */
    protected function constructFieldMeta(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $name = $field['name'] ?? $field['id'] ?? $key;
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
    public function getKeys(): array
    {
        return array_keys($this->getMeta());
    }

    /**
     * Indicates whether the given key defines a field on the posted form.
     */
    public function isKey(string $key): bool
    {
        $fieldMeta = $this->getMeta();
        return isset($fieldMeta[$key]);
    }

    /**
     * Indicates whether the given key defines an array field.
     */
    public function isArray(string $key): bool
    {
        $fieldMeta = $this->getMeta();
        return isset($fieldMeta[$key]) && str_ends_with($fieldMeta[$key]->name, '[]');
    }

    /**
     * Indicates whether the given key defines a checkbox field.
     */
    public function isCheckbox(string $key): bool
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
     */
    public function getPostedValues(): array
    {
        $result = $_POST;
        $result = $this->alterPostedValues($result);
        unset($result[static::Meta]);
        return $result;
    }

    /**
     * Allows altering the posted values in a webshop-specific way.
     *
     * This basic implementation returns the unaltered set of posted values.
     */
    protected function alterPostedValues(array $postedValues): array
    {
        return $postedValues;
    }

    /**
     * Allows altering the form values in a webshop-specific way.
     *
     * This basic implementation returns the unaltered set of form values.
     *
     * @param array $formValues
     *   A flat set of values for the form elements, keyed by the name of the element.
     * @param array $fields
     *   The hierarchical set of field definitions, keyed by name.
     *
     * @return array
     *   The altered set of values for the form elements.
     */
    public function alterFormValues(array $formValues, array $fields): array
    {
        return $formValues;
    }

    /**
     * Adds a severity CSS class to form fields that do have a message.
     */
    public function addSeverityClassToFields(array $fields, array $messages): array
    {
        foreach ($messages as $message) {
            if (!empty($message->getField())) {
                $this->addSeverityClassToField($fields, $message->getField(), $this->severityToCssClass($message->getSeverity()));
            }
        }
        return $fields;
    }

    /**
     * Adds a severity CSS class to a form field.
     */
    protected function addSeverityClassToField(array &$fields, string $id, string $severityClass): void
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
     * Returns a CSS class for a given severity level.
     */
    protected function severityToCssClass(int $severity): string
    {
        return match ($severity) {
            Severity::Exception, Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Notice => 'notice',
            Severity::Info => 'info',
            Severity::Success => 'success',
            default => '',
        };
    }

    /**
     * Process all fields.
     *
     * @param array[] $fields
     *
     * @return array[]
     *   The processed fields.
     */
    public function processFields(array $fields): array
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
     */
    protected function processField(array $field, string $key): array
    {
        // Add help text to 'details' fields.
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
