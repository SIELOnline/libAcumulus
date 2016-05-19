<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;
use Tools;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /** @var string */
    protected $moduleName;

    /**
     * ConfigForm constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Shop\Config $config
     */
    public function __construct(TranslatorInterface $translator, Config $config)
    {
        parent::__construct($translator, $config);
        $this->moduleName = 'acumulus';
    }

    /**
     * {@inheritdoc}
     *
     * This override uses the PS way of checking if a form is submitted.
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submit' . $this->moduleName);
    }

    /**
     * {@inheritdoc}
     *
     * This override ensures that array values are passed with the correct key
     * to the PS form renderer.
     */
    public function getFormValues()
    {
        $result = parent::getFormValues();
        $result['triggerOrderStatus[]'] = $result['triggerOrderStatus'];
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function setFormValues()
    {
        parent::setFormValues();

        // Prepend (checked) checkboxes with their collection name.
        foreach ($this->getCheckboxKeys() as $checkboxName => $collectionName) {
            if (isset($this->formValues[$checkboxName])) {
                $this->formValues["{$collectionName}_{$checkboxName}"] = $this->formValues[$checkboxName];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();

        // Add icons.
        $result['accountSettingsHeader']['icon'] = 'icon-user';
        $result['invoiceSettingsHeader']['icon'] = 'icon-AdminParentPreferences';
        if (isset($result['emailAsPdfSettingsHeader'])) {
            $result['emailAsPdfSettingsHeader']['icon'] = 'icon-file-pdf-o';
        }
        $result['versionInformationHeader']['icon'] = 'icon-info-circle';

        // Flatten fieldsets in invoice settings by inserting them after that
        // fieldset.
        $fields = &$result['invoiceSettingsHeader']['fields'];
        $id = 'paymentMethodAccountNumberFieldset';
        if (isset($fields[$id])) {
            $insert[$id] = $fields[$id];
            $insert[$id]['icon'] = 'icon-credit-card';
            unset($fields[$id]);

            $id = 'paymentMethodCostCenterFieldset';
            $insert[$id] = $fields[$id];
            $insert[$id]['icon'] = 'icon-credit-card';
            unset($fields[$id]);

            array_splice($result, 2, 0, $insert);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopOrderStatuses()
    {
        $states = OrderState::getOrderStates((int) Context::getContext()->language->id);
        $result = array();
        foreach ($states as $state) {
            $result[$state['id_order_state']] = $state['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice sent' option as PS does not have
     * an event on creating/sending the invoice.
     *
     * @todo: PS has the 'actionSetInvoice' event, can we use that?
     * This event fires when the order state changes to a state that allows an
     * invoice and on manually creating one via the adminOrdersController page.
     */
    protected function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentMethods()
    {
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $result = array();
        foreach($paymentModules as $paymentModule)
        {
            $module = Module::getInstanceById($paymentModule['id_module']);
            $result[$module->name] = $module->displayName;
        }
        return $result;
    }
}
