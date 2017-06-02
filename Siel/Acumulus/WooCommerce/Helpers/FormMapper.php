<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Form;

/**
 * FormMapper maps an Acumulus form definition to a WooCommerce form definition.
 */
class FormMapper
{
    const required = '<span class="required">*</span>';

    /** @var string */
    protected $page;

    /**
     * Maps a set of field definitions.
     *
     * @param Form $form
     *   The form to map to a WordPress form.
     * @param string $page
     *   The slug-name of the settings page on which to show the section.
     * @param callable $callback
     *   The callback to actually render a field.
     */
    public function map(Form $form, $page, $callback)
    {
        $this->page = $page;
        $form->addValues();
        $this->fields($form->getFields(), '', $callback);
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     * @param string $section
     *   The page section to add the fields to.
     * @param callable $callback
     *   The callback to actually render a field.
     */
    protected function fields(array $fields, $section, $callback)
    {
        foreach ($fields as $id => $field) {
            $field['id'] = $id;
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            if (!isset($field['attributes'])) {
                $field['attributes'] = array();
            }
            if (!isset($field['label'])) {
                $field['label'] = '';
            }
            $this->field($field, $section, $callback);
        }
    }

    /**
     * Maps a single field definition.
     *
     * @param array $field
     *   Field(set) definition.
     * @param string $section
     *   The section this item (if it is a field) should be added to.
     * @param callable $callback
     *   The callback to actually render this field.
     */
    protected function field(array $field, $section, callable $callback)
    {
        if ($field['type'] === 'fieldset') {
            add_settings_section($field['id'], $field['legend'], function () use ($callback, $field) {
                $callback($field);
            }, $this->page);
            $fields = $field['fields'];
            if (!empty($field['description'])) {
                $descriptionField = array(
                    'type' =>'markup',
                    'label' => '<span class="fieldset-description-label">🛈</span>',
                    'id' => $field['id'] . '-description',
                    'value' => "<div class='fieldset-description'>{$field['description']}</div>",
                );
                array_unshift($fields, $descriptionField);
            }
            $this->fields($fields, $field['id'], $callback);
        } else {
            $required = !empty($field['attributes']['required']) ? static::required : '';
            add_settings_field($field['id'], $field['label'] . $required, $callback, $this->page, $section, $field);
        }
    }
}
