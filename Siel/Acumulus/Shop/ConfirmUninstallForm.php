<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;

/**
 * Class ConfirmUninstallForm
 */
class ConfirmUninstallForm extends Form
{
    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct($translator);

        $translations = new ConfirmUninstallFormTranslations();
        $this->translator->add($translations);
    }

    /**
     * Executes the form action on valid form submission.
     *
     * Override to implement the actual form handling, like saving values.
     *
     * @return bool
     *   Success.
     */
    protected function execute()
    {
        // @todo: Implement execute() method.
        return true;
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
    protected function getFieldDefinitions()
    {
        $fields = array();

        // 1st fieldset: Confirm uninstall message.
        $fields['uninstallHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('uninstallHeader'),
            'fields' => array(
                'uninstall_message' =>  array(
                    'type' => 'markup',
                    'value' => $this->t('desc_uninstall'),
                ),
            ),
        );

        return $fields;
    }
}
