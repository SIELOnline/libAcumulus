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

    /** @var FormRenderer */
    protected $formRenderer;

    /**
     * Maps a set of field definitions.
     *
     * @param Form $form
     * @param string $page
     *
     * @return \Siel\Acumulus\WooCommerce\Helpers\FormRenderer
     */
    public function map(Form $form, $page)
    {
        $this->formRenderer = new FormRenderer();
        $this->page = $page;
        $form->addValues();
        $this->fields($form->getFields(), '');
        return $this->formRenderer;
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     * @param string $section
     *   The page section to add the fields to.
     */
    protected function fields(array $fields, $section)
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
            $this->field($field, $section);
        }
    }

    /**
     * Maps a single field definition.
     *
     * @param array $field
     *   Field(set) definition.
     * @param string $section
     *   The section this item (if it is a field) should be added to.
     */
    protected function field(array $field, $section)
    {
        if ($field['type'] === 'fieldset') {
            $renderer = $this->formRenderer;
            add_settings_section($field['id'], $field['legend'], function () use ($renderer, $field) {
                $renderer->field($field);
            }, $this->page);
            $this->fields($field['fields'], $field['id']);
        } else {
            $required = !empty($field['attributes']['required']) ? static::required : '';
            add_settings_field($field['id'], $field['label'] . $required, array($this->formRenderer, 'field'), $this->page, $section, $field);
        }
    }
}
