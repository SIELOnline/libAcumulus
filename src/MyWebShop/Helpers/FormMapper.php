<?php
namespace Siel\Acumulus\MyWebShop\Helpers;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormMapper as BaseFormMapper;

/**
 * FormMapper maps an Acumulus form definition to a MyWebShop form definition.
 *
 * @todo: delete ths file if MyWebshop does not provide its own form subsystem,use a FormRenderer in that case.
 *
 * You may want to have a look at how the PrestaShop or Magneto mapper do
 * their work. The PrestaShop mapper has to return an array of field
 * definitions, while the Magento mapper gets a Magento Form object to which is
 * has to add the fields.
 *
 * However, whatever MyWebshop expects, you probably best follow the top-down
 * approach by:
 * * keeping and adapting the fields() and field() methods
 * * creating a method per (group of) form field type(s), like e.g. fieldset(),
 *   element, input(), radio(), ...
 */
class FormMapper extends BaseFormMapper
{
    /**
     * {@inheritdoc}
     */
    public function map(Form $form)
    {
        // @todo: adapt to the way MyWebshop wants you to create a form.
        return $this->fields($form->getFields());
    }

    /**
     * Maps a set of field definitions.
     *
     * @param array[] $fields
     *
     * @return array[]
     */
    protected function fields(array $fields)
    {
        // @todo: adapt to the way MyWebshop wants you to create a form,
        //  e.g. adding the Form object as parameter, or creating and returning
        //  a Form object.
        $result = [];
        foreach ($fields as $id => $field) {
            if (!isset($field['id'])) {
                $field['id'] = $id;
            }
            if (!isset($field['name'])) {
                $field['name'] = $id;
            }
            $result[$id] = $this->field($field);
        }
        return $result;
    }

    /**
     * Maps a single field definition, possibly a fieldset.
     *
     * @param array $field
     *   Field(set) definition.
     *
     * @return array
     */
    protected function field(array $field)
    {
        // @todo: adapt to the way MyWebshop wants you to create a form,
        //  e.g. adding the Form object as parameter, or creating and returning
        //  a FormField object.
        if ($field['type'] === 'fieldset') {
            $result = $this->fieldset($field);
        } else {
            $result = $this->element($field);
        }
        return $result;
    }

    /**
     * Returns a mapped fieldset.
     *
     * @param array $field
     *   An Acumulus fieldset definition.
     *
     * @return array[]
     *   A MyWebShop fieldset definition, including its fields.
     */
    protected function fieldset(array $field)
    {
        $result = [];

        // @todo: adapt to the way MyWebshop wants you to define a fieldset.

        return $result;
    }

    /**
     * Returns a mapped simple element.
     *
     * @param array $field
     *   An Acumulus form field definition.
     *
     * @return array
     *   A MyWebShop form field definition.
     */
    protected function element(array $field)
    {
        $result = [];

        // @todo: adapt to the way MyWebshop wants you to define a simple form field.

        return $result;
    }
}
