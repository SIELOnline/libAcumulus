<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\Config as ShopConfig;
use Siel\Acumulus\Shop\ModuleTranslations;
use Siel\Acumulus\Web\ConfigInterface;

/**
 * OcHelper contains functionality shared between the OC1 and OC2 controllers
 * and models, for both admin and catalog.
 */
class OcHelper
{
    /** @var \Siel\Acumulus\Shop\Config */
    protected $acumulusConfig = null;

    /** @var array */
    public $data;

    /** @var \Siel\Acumulus\Helpers\Form */
    protected $form;

    /** @var \Registry */
    protected $registry;

    /** @var string */
    protected $shopNamespace;

    /**
     * OcHelper constructor.
     *
     * @param \Registry $registry
     * @param string $shopNamespace
     */
    public function __construct(\Registry $registry, $shopNamespace)
    {
        Registry::setRegistry($registry);
        $this->registry = Registry::getInstance();
        $this->shopNamespace = $shopNamespace;
    }

    protected function addError($message)
    {
        if (is_array($message)) {
            $this->data['error_messages'] = array_merge($this->data['error_messages'], $message);
        } else {
            $this->data['error_messages'][] = $message;
        }
    }

    protected function addSuccess($message)
    {
        $this->data['success_messages'][] = $message;
    }

    /**
     * Helper method that initializes some object properties:
     * - language
     * - model_Setting_Setting
     * - webAPI
     * - acumulusConfig
     */
    protected function init()
    {
        if ($this->acumulusConfig === null) {
            $languageCode = $this->registry->language->get('code');
            if (empty($languageCode)) {
                $languageCode = 'nl';
            }
            $this->acumulusConfig = new ShopConfig($this->shopNamespace, $languageCode);
            $this->acumulusConfig->getTranslator()->add(new ModuleTranslations());
        }
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
        return $this->acumulusConfig->getTranslator()->get($key);
    }

    /**
     * Install controller action, called when the module is installed.
     *
     * @return bool
     */
    public function install()
    {
        // Call the actual install method.
        $this->doInstall();

        return empty($this->data['error_messages']);
    }

    /**
     * Uninstall function, called when the module is uninstalled by an admin.
     */
    public function uninstall()
    {
        $this->registry->response->redirect($this->registry->url->link('module/acumulus/confirmUninstall', 'token=' . $this->registry->session->data['token'], 'SSL'));
    }

    /**
     * Controller action: show/process the settings form for this module.
     */
    public function config()
    {
        $this->displayFormCommon('config');

        // Are we posting? If not so, handle this as a trigger to update.
        if ($this->registry->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->doUpgrade();
        }

        // Add an intermediate level to the breadcrumb.
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t('modules'),
            'href' => Registry::getInstance()->url->link('extension/module', 'token=' . $this->registry->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->renderFormCommon('config', 'button_save');
    }

    /**
     * Controller action: show/process the settings form for this module.
     */
    public function batch()
    {
        $this->displayFormCommon('batch');
        $this->renderFormCommon('batch', 'button_send');
    }

    /**
     * Explicit confirmation step to allow to retain the settings.
     *
     * The normal uninstall action will unconditionally delete all settings.
     */
    public function confirmUninstall()
    {
        // @todo: implement uninstall form
//        $this->displayFormCommon('uninstall');
//
//        // Are we confirming, or should we show the confirm message?
//        if ($this->registry->request->server['REQUEST_METHOD'] === 'POST') {
            $this->doUninstall();
            $this->registry->response->redirect($this->registry->url->link('extension/module', 'token=' . $this->registry->session->data['token'],
                'SSL'));
//        }
//
//        // Add an intermediate level to the breadcrumb.
//        $this->data['breadcrumbs'][] = array(
//            'text' => $this->t('modules'),
//            'href' => $this->registry->url->link('extension/module', 'token=' . $this->registry->session->data['token'], 'SSL'),
//            'separator' => ' :: '
//        );
//
//        $this->renderFormCommon('confirmUninstall', 'button_confirm_uninstall');
    }

    /**
     * Event handler that executes on the creation or update of an order.
     *
     * @param int $order_id
     */
    public function eventOrderUpdate($order_id) {
        $this->init();
        $source = $this->acumulusConfig->getSource(Source::Order, $order_id);
        $this->acumulusConfig->getManager()->sourceStatusChange($source);
    }

    /**
     * Performs the common tasks when displaying a form.
     *
     * @param string $task
     */
    protected function displayFormCommon($task)
    {
        $this->init();

        $this->form = $this->acumulusConfig->getForm($task);

        $this->registry->document->addStyle('view/stylesheet/acumulus.css');

        $this->data['success_messages'] = array();
        $this->data['error_messages'] = array();

        // Set the page title.
        $this->registry->document->setTitle($this->t("{$task}_form_title"));
        $this->data["page_title"] = $this->t("{$task}_form_title");
        $this->data["heading_title"] = $this->t("{$task}_form_header");
        $this->data["text_edit"] = $this->t("{$task}_form_header");

        // Set up breadcrumb.
        $this->data['breadcrumbs'] = array();
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t('text_home'),
            'href' => $this->registry->url->link('common/dashboard', 'token=' . $this->registry->session->data['token'], 'SSL'),
            'separator' => false
        );
    }

    /**
     * Performs the common tasks when processing and rendering a form.
     *
     * @param string $task
     * @param string $button
     */
    protected function renderFormCommon($task, $button)
    {
        // Process the form if it was submitted and render it again.
        $this->form->process();

        // Show messages.
        foreach ($this->form->getSuccessMessages() as $message) {
            $this->addSuccess($message);
        }
        foreach ($this->form->getErrorMessages() as $message) {
            $this->addError($this->t($message));
        }

        $this->data['form'] = $this->form;
        $this->data['formRenderer'] = $this->acumulusConfig->getFormRenderer();

        // Complete the breadcrumb with the current path.
        $link = 'module/acumulus';
        if ($task !== 'config') {
            $link .= "/$task";
        }
        $this->data['breadcrumbs'][] = array(
            'text' => $this->t("{$task}_form_header"),
            'href' => $this->registry->url->link($link, 'token=' . $this->registry->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        // Set the action buttons (action + text).
        $this->data['action'] = $this->registry->url->link($link, 'token=' . $this->registry->session->data['token'], 'SSL');
        $this->data['button_icon'] = $task === 'batch' ? 'fa-envelope-o' : ($task === 'uninstall' ? 'fa-delete' : 'fa-save');
        $this->data['button_save'] = $this->t($button);
        $this->data['cancel'] = $this->registry->url->link('common/dashboard', 'token=' . $this->registry->session->data['token'], 'SSL');
        $this->data['button_cancel'] = $task === 'uninstall' ? $this->t('button_cancel_uninstall') : $this->t('button_cancel');
    }

    /**
     * Checks requirements and installs tables for this module.
     *
     * @return bool
     *   Success.
     */
    protected function doInstall()
    {
        $this->init();

        $result = true;
        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        $currentDataModelVersion = isset($setting['acumulus_siel_datamodel_version']) ? $setting['acumulus_siel_datamodel_version'] : '';

        if ($currentDataModelVersion === '' || version_compare($currentDataModelVersion, '4.0', '<')) {
            // Check requirements (we assume this has been done successfully
            // before if the data model is at the latest version).
            $requirements = new Requirements();
            $messages = $requirements->check();
            foreach ($messages as $message) {
                $this->addError($message['message']);
            }
            if (!empty($messages)) {
                return false;
            }

            // Install tables.
            if ($result = $this->acumulusConfig->getAcumulusEntryModel()->install()) {
                $setting['acumulus_siel_datamodel_version'] = '4.0';
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        }
        else if (version_compare($currentDataModelVersion, '4.4', '<')) {
            // Update table columns.
            if ($result = $this->acumulusConfig->getAcumulusEntryModel()->upgrade('4.4.0')) {
                $setting['acumulus_siel_datamodel_version'] = '4.4';
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        }

        return $result;
    }

    /**
     * Uninstalls data and settings from this module.
     *
     * @return bool
     *   Whether the uninstall was successful.
     */
    protected function doUninstall()
    {
        $this->init();
        $this->acumulusConfig->getAcumulusEntryModel()->uninstall();

        // Delete all config values.
        $this->registry->load->model('setting/setting');
        $this->registry->model_setting_setting->deleteSetting('acumulus_siel');

        return true;
    }

    /**
     * Upgrades the data and settings for this module if needed.
     *
     * The install now checks for the data model and can do an upgrade instead
     * of a clean install.
     *
     * @return bool
     *   Whether the upgrade was successful.
     */
    protected function doUpgrade()
    {
        $this->init();

        //Install/update datamodel first.
        $result = $this->doInstall();

        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        $currentDataModelVersion = isset($setting['acumulus_siel_datamodel_version']) ? $setting['acumulus_siel_datamodel_version'] : '';
        $apiVersion = ConfigInterface::libraryVersion;

        if (version_compare($currentDataModelVersion, $apiVersion, '<')) {
            // Update config settings.
            if ($result = $this->acumulusConfig->upgrade($currentDataModelVersion)) {
                // Refresh settings.
                $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
                $setting['acumulus_siel_datamodel_version'] = $apiVersion;
                $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
            }
        }

        return $result;
    }
}
