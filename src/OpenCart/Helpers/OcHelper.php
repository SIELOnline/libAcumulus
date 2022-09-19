<?php
/**
 * Note: we should not use PHP7 language constructs in this class. See e.g.
 * {@see \Siel\Acumulus\Helpers\Log} for more information.
 *
 * The PHP7 language constructs we suppress the warnings for:
 *
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 * @noinspection PhpConcatenationWithEmptyStringCanBeInlinedInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace Siel\Acumulus\OpenCart\Helpers;

use Exception;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use stdClass;

use Throwable;

use const Siel\Acumulus\Version;

/**
 * Class OcHelper contains functionality shared between the controllers and
 * models of the different OC versions, for both admin and catalog.
 *
 * However, even if at this moment we are only supporting 1 OC version, we keep
 * this functionality in the library to keep the code in the weird OC structure
 * to a minimum.
 */
class OcHelper
{
    /** @var \Siel\Acumulus\Helpers\Container */
    protected $acumulusContainer = null;

    /** @var array */
    public $data;

    /** @var \Siel\Acumulus\OpenCart\Helpers\Registry */
    protected $registry;

    /**
     * OcHelper constructor.
     *
     * @param \Registry $registry
     * @param \Siel\Acumulus\Helpers\Container $acumulusContainer
     */
    public function __construct(\Registry $registry, Container $acumulusContainer)
    {
        $this->acumulusContainer = $acumulusContainer;
        $this->registry = $this->acumulusContainer->getInstance('Registry', 'Helpers', [$registry]);
        $this->data = [];

        $languageCode = $this->registry->language->get('code');
        if (empty($languageCode)) {
            $languageCode = 'nl';
        }
        $this->acumulusContainer->setLanguage($languageCode);
    }

    /**
     * Adds the messages to the respective sets of messages in $data.
     *
     * @param Message[] $messages
     */
    protected function addMessages($messages)
    {
        foreach ($messages as $message) {
            switch ($message->getSeverity()) {
                case Severity::Log:
                case Severity::Success:
                case Severity::Info:
                case Severity::Notice:
                    $dataKey = 'success_messages';
                    break;
                case Severity::Warning:
                    $dataKey = 'warning_messages';
                    break;
                case Severity::Error:
                case Severity::Exception:
                    $dataKey = 'error_messages';
                    break;
                default:
                    $dataKey = '';
                    break;
            }
            if (!empty($dataKey)) {
                $this->data[$dataKey][] = $message->format(Message::Format_PlainWithSeverity);
            }
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
        return $this->acumulusContainer->getTranslator()->get($key);
    }

    /**
     * Returns the location of the module.
     *
     * @return string
     *   The location of the module.
     */
    public function getLocation()
    {
        return $this->registry->getLocation();
    }

    /**
     * Install controller action, called when the module is installed.
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function install()
    {
        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        $isAlreadyInstalled = count($setting) > 0;

        if (!$isAlreadyInstalled) {
            // Call the actual install method.
            $result = $this->doInstall();
        } else {
            // Config already exists:this is not a clean install: upgrade.
            $result = $this->doUpgrade();
        }
        return $result;
    }

    /**
     * Uninstall function, called when the module is uninstalled by an admin.
     *
     * @throws \Exception
     */
    public function uninstall()
    {
        // "Disable" (delete) events, regardless the confirmation answer.
        $this->uninstallEvents();
        $this->doUninstall();
        $this->registry->response->redirect($this->registry->getLink($this->getRedirectUrl()));
    }

    /**
     * Controller action: show/process the settings form.
     *
     * @throws \Throwable
     */
    public function config()
    {
        $this->handleForm('config');
    }

    /**
     * Controller action: show/process the advanced settings form.
     *
     * @throws \Throwable
     */
    public function advancedConfig()
    {
        $this->handleForm('advanced');
    }

    /**
     * Controller action: show/process the batch form.
     *
     * @throws \Throwable
     */
    public function batch()
    {
        $this->handleForm('batch');
    }

    /**
     * Controller action: show/process the register form.
     *
     * @throws \Throwable
     */
    public function activate()
    {
        $this->handleForm('activate');
    }

    /**
     * Controller action: show/process the register form.
     *
     * @throws \Throwable
     */
    public function register()
    {
        $this->handleForm('register');
    }

    /**
     * Handles a form controller action.
     *
     * @throws \Throwable
     */
    public function handleForm(string $type): void
    {
        try {
            $this->initFormFullPage($type);
            $this->processFormCommon();
            $this->renderFormCommon();
        } catch (Throwable $e) {
            $this->reportCrash($e);
        }
        $this->outputForm();
    }

    /**
     * Adds our status overview as a tab to the order info view.
     *
     * @param int $orderId
     *   The order id to show the Acumulus invoice status for.
     * @param array $tabs
     *   The tabs that will be displayed along the 'History' and 'Additional'
     *   tabs.
     *
     * @throws \Throwable
     */
    public function eventViewSaleOrderInfo(int $orderId, array &$tabs)
    {
        if ($this->acumulusContainer->getConfig()->getInvoiceStatusSettings()['showInvoiceStatus']) {
            try {
                $type = 'invoice';
                $this->initFormInvoice();// Set order.
                /** @var \Siel\Acumulus\Shop\InvoiceStatusForm $form */
                $form = $this->data['form'];
                $form->setSource($this->acumulusContainer->createSource(Source::Order, $orderId));
                $this->processFormCommon();
                $this->renderFormCommon();
            } catch (Throwable $e) {
                $this->reportCrash($e);
            }

            $tab = new stdClass();
            $tab->code = "acumulus-$type";
            $tab->title = $this->t("{$type}_form_title");
            $tab->content = $this->outputForm(true);
            $tabs[] = $tab;
        }
    }

    /**
     * Controller ajax action: process and refresh the invoice status form.
     *
     * @throws \Throwable
     */
    public function invoice()
    {
        try {
            $this->initFormInvoice();
            $this->processFormCommon();
            $this->renderFormCommon();
        } catch (Throwable $e) {
            $this->reportCrash($e);
        }
        // Send the output.
        $this->registry->response->addHeader('Content-Type: application/json;charset=utf-8');
        $this->registry->response->setOutput(json_encode(['content' => $this->outputForm(true)]));
    }

    /**
     * @throws \Throwable
     */
    public function reportCrash(Throwable $e): void
    {
        // We handle our "own" exceptions but only when we can process them as
        // we want, i.e. show it as an error at the beginning of the form.
        // That's why we handle only when we have a form, and stop catching
        // just before outputForm(). If we do not have a form we rethrow the
        // exception.
        if (isset($this->data['form'])) {
            try {
                $form = $this->data['form'];
                $crashReporter = $this->acumulusContainer->getCrashReporter();
                $message = $crashReporter->logAndMail($e);
                $form->createAndAddMessage($message, Severity::Exception);
            } catch (Throwable $inner) {
                // We do not know if we have informed the user per mail or
                // screen, so assume not, and rethrow the original exception.
                throw $e;
            }
        } else {
            throw $e;
        }
    }

    protected function initFormInvoice(): void
    {
        $type = 'invoice';
        $this->initFormCommon($type);
        $this->data['id'] = "acumulus-$type";
        $this->data['action'] = $this->acumulusContainer->getShopCapabilities()->getLink($type);
        $this->data['wait'] = $this->t('wait');
    }

    /**
     * Performs the common tasks when displaying a form.
     */
    protected function initFormFullPage(string $type)
    {
        $this->initFormCommon($type);

        $this->registry->document->addStyle('view/stylesheet/acumulus.css');

        $this->data['header'] = $this->registry->load->controller('common/header');
        $this->data['column_left'] = $this->registry->load->controller('common/column_left');
        $this->data['footer'] = $this->registry->load->controller('common/footer');

        // Set headers and titles.
        $this->registry->document->setTitle($this->t("{$type}_form_title"));
        $this->data["page_title"] = $this->t("{$type}_form_title");
        $this->data["text_edit"] = $this->t("{$type}_form_header");

        $link = $this->getLocation();
        if ($type !== 'config') {
            $link .= "/$type";
        }

        // Set up breadcrumb.
        $this->data['breadcrumbs'] = [];
        $this->data['breadcrumbs'][] = [
            'text' => $this->t('text_home'),
            'href' => $this->registry->getLink('common/dashboard'),
            'separator' => false
        ];
        // Add an intermediate level to the config breadcrumb.
        if ($type === 'config') {
            $this->data['breadcrumbs'][] = $this->getExtensionsBreadcrumb();
        }
        $this->data['breadcrumbs'][] = [
            'text' => $this->t("{$type}_form_header"),
            'href' => $this->registry->getLink($link),
            'separator' => ' :: '
        ];

        // Set the action buttons (action + text).
        $this->data['action'] = $this->registry->getLink($link);
        $this->data['button_icon'] = $type === 'batch' ? 'fa-envelope-o'
            : ($type === 'uninstall' ? 'fa-delete'
            : (in_array($type,['activate', 'register']) ? 'fa-plus'
            : 'fa-save'));
        $this->data['button_save'] = $this->t("button_submit_$type");
        $this->data['cancel'] = $this->registry->getLink('common/dashboard');
        $this->data['button_cancel'] = $type === 'uninstall' ? $this->t('button_cancel_uninstall') : $this->t('button_cancel');
    }

    /**
     * @param string $type
     *
     * @return void
     */
    public function initFormCommon(string $type): void
    {
        // Are we posting? If not so, handle this as a trigger to update.
        if ($this->registry->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->doUpgrade();
        }

        // This will initialize the form translations.
        $this->data['form'] = $this->acumulusContainer->getForm($type);
        $this->data['formRenderer'] = $this->acumulusContainer->getFormRenderer();

        $this->data['success_messages'] = [];
        $this->data['warning_messages'] = [];
        $this->data['error_messages'] = [];

        $this->data['heading_title'] = $this->t("{$type}_form_header");
    }

    /**
     * Processes the form if it was submitted.
     */
    public function processFormCommon(): void
    {
        /** @var \Siel\Acumulus\Helpers\Form $form */
        $form = $this->data['form'];
        $form->process();
    }

    /**
     * Renders the form using the view.
     */
    protected function renderFormCommon(): void
    {
        /** @var \Siel\Acumulus\Helpers\FormRenderer $formRenderer */
        $formRenderer = $this->data['formRenderer'];
        $this->data['formHtml'] = $formRenderer->render($this->data['form']);
    }

    /**
     * Outputs the form.
     */
    protected function outputForm(bool $return = false): ?string
    {
        // Pass messages to twig template.
        /** @var \Siel\Acumulus\Helpers\Form $form */
        $form = $this->data['form'];
        $this->addMessages($form->getMessages());

        $route = $this->getLocation();
        if ($form->getType() === 'invoice') {
            $route .= '_invoice';
        }
        $route .= '_form';
        $output = $this->registry->load->view($route, $this->data);
        // Send or return the output.
        if ($return) {
            return $output;
        } else {
            $this->registry->response->setOutput($output);
            return null;
        }
    }

    /**
     * Returns the url to redirect to after the uninstallation action completes.
     *
     * @return string
     *   The url to redirect to after uninstall.
     */
    protected function getRedirectUrl()
    {
        return 'marketplace/extension';
    }

    /**
     * Returns the intermediate breadcrumb for the config screen.
     *
     * The config screen is normally accessed via the extensions part of
     * OpenCart. Therefore, an intermediate level is added to the breadcrumb,
     * consisting of the extensions page.
     *
     * @return array
     *   The intermediate breadcrumb for the config screen.
     */
    protected function getExtensionsBreadcrumb()
    {
        return [
            'text' => $this->t('extensions'),
            'href' => Registry::getInstance()->getLink('marketplace/extension'),
            'separator' => ' :: '
        ];
    }

    /**
     * Extracts the order id of the parameters as passed to the event handler.
     *
     * Where the order id can be found depends on the route.
     *
     * @param array $args
     *   The arguments passed to the event handler. $args will contain:
     *   - string, route: ('checkout/order/addOrder' or
     *     'checkout/order/addOrderHistory').
     *   - array, args: array with numeric indices containing the arguments as
     *     passed to the model method (that is triggering the event):
     *     - route = checkout/order/addOrder:
     *       * order (but without order_id as that will be created and assigned
     *         by the method).
     *     - route = checkout/order/addOrderHistory:
     *       * order_id
     *       * order_status_id
     *       * comment
     *       * notify
     *       * override.
     *   - mixed, $output: what the model method that is triggering the event
     *     is about to return.
     *     - route = checkout/order/addOrder: order_id of the just created order.
     *     - route = checkout/order/addOrderHistory: null.
     *
     * @return int
     *   The id of the order that triggered the event.
     */
    public function extractOrderId(array $args)
    {
        $route = $args[0];
        $event_args = $args[1];
        $output = $args[2];
        $order_id = substr($route, -strlen('/addOrder')) ===  '/addOrder' ? $output : $event_args[0];
        return (int) $order_id;
    }

    /**
     * Event handler that executes on the creation or update of an order.
     *
     * @param int $order_id
     */
    public function eventOrderUpdate($order_id)
    {
        $source = $this->acumulusContainer->createSource(Source::Order, $order_id);
        $this->acumulusContainer->getInvoiceManager()->sourceStatusChange($source);
    }

    /**
     * Adds our menu-items to the admin menu.
     *
     * @param array $menus
     *   The menus part of the data as will be passed to the view.
     */
    public function eventViewColumnLeft(&$menus)
    {
        foreach ($menus as &$menu) {
            if ($menu['id'] === 'menu-sale') {
                $menu['children'][] = [
                    'name' => 'Acumulus',
                    'href' => '',
                    'children' => [
                        [
                            'name' => $this->t('batch_form_link_text'),
                            'href' => $this->acumulusContainer->getShopCapabilities()->getLink('batch'),
                            'children' => [],
                        ],
                        [
                            'name' => $this->t('config_form_link_text'),
                            'href' => $this->acumulusContainer->getShopCapabilities()->getLink('config'),
                            'children' => [],
                        ],
                        [
                            'name' => $this->t('advanced_form_link_text'),
                            'href' => $this->acumulusContainer->getShopCapabilities()->getLink('advanced'),
                            'children' => [],
                        ],
                        [
                            'name' => $this->t('activate_form_link_text'),
                            'href' => $this->acumulusContainer->getShopCapabilities()->getLink('activate'),
                            'children' => [],
                        ],
                    ],
                ];
            }
        }
    }

    /**
     * Adds css and js to our status overview on the order info view.
     */
    public function eventControllerSaleOrderInfo()
    {
        if ($this->acumulusContainer->getConfig()->getInvoiceStatusSettings()['showInvoiceStatus']) {
            $this->registry->document->addStyle('view/stylesheet/acumulus.css');
            $this->registry->document->addScript('view/javascript/acumulus/acumulus-ajax.js');
        }
    }

    /**
     * Performs a clean installation.
     * Checks requirements and installs tables and initial config.
     *
     * @return bool
     *   Success.
     *
     * @noinspection PhpDocMissingThrowsInspection*/
    protected function doInstall()
    {
        $requirements = $this->acumulusContainer->getRequirements();
        $messages = $requirements->check();
        foreach ($messages as $message) {
            $this->addMessages([Message::create($message, Severity::Error)]);
            $this->acumulusContainer->getLog()->error($message);
        }
        if (!empty($messages)) {
            return false;
        }

        // Install tables.
        try {
            $result = $this->acumulusContainer->getAcumulusEntryManager()->install();
        } catch (Exception $e) {
            $this->acumulusContainer->getLog()->error('Exception installing tables: ' . $e->getMessage());
            $result = false;
        }

        // Install events.
        try {
            $this->installEvents();
        } catch (Exception $e) {
            $this->acumulusContainer->getLog()->error('Exception installing events: ' . $e->getMessage());
            $result = false;
        }

        // Install initial config.
        if ($result) {
            $this->acumulusContainer->getConfig()->save([Config::configVersion => Version]);
        }

        $this->acumulusContainer->getLog()->info('%s: installed version = %s, $result = %s', __METHOD__, Version, $result ? 'true' : 'false');
        return $result;
    }

    /**
     * Uninstalls data and settings from this module.
     *
     * @return bool
     *   Whether the uninstallation was successful.
     *
     * @throws \Exception
     */
    protected function doUninstall()
    {
        $this->acumulusContainer->getAcumulusEntryManager()->uninstall();

        // Delete all config values.
        $this->registry->load->model('setting/setting');
        $this->registry->model_setting_setting->deleteSetting('acumulus_siel');

        return true;
    }

    /**
     * Upgrades the data and settings for this module if needed.
     *
     * @return bool
     *   Whether the upgrade was successful.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function doUpgrade()
    {
        if (!empty($this->acumulusContainer->getConfig()->get(Config::configVersion))) {
            // Config updates are now done in the config itself and, for now, no
            // data model changes have been made since the introduction of
            // configVersion, so we can return.
            return true;
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->registry->load->model('setting/setting');
        $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
        // if  'acumulus_siel_datamodel_version' is not set, we must be coming
        // from a version before it was introduced. I have no idea when that
        // was, but let's say we pick every update as of version 4.0:
        $currentDataModelVersion = $setting['acumulus_siel_datamodel_version'] ?? '4.0.0-beta1';

        // Update or even install table.
        if ($currentDataModelVersion === '' || version_compare($currentDataModelVersion, '4.0', '<')) {
            // Check requirements before we continue upgrading from such an old
            // version, because this also means that the previous requirements
            // check also dates back from the PHP 5.3 era.
            // @todo: extract into separate method.
            $requirements = $this->acumulusContainer->getRequirements();
            $messages = $requirements->check();
            foreach ($messages as $key => $message) {
                $severity = strpos($key, 'warning') !== false ? Severity::Warning : Severity::Error;
                $this->addMessages([Message::create($message, $severity)]);
                $this->acumulusContainer->getLog()->log($severity, $message);
            }
            if (!empty($messages)) {
                return false;
            }

            // Install tables.
            $result = $this->acumulusContainer->getAcumulusEntryManager()->install();
        } else {
            $result = $this->acumulusContainer->getAcumulusEntryManager()->upgrade($currentDataModelVersion);
        }

        // Install events (just to be sure).
        try {
            $this->installEvents();
        } catch (Exception $e) {
            $this->acumulusContainer->getLog()->error('Exception installing events: ' . $e->getMessage());
            $result = false;
        }

        // Update config values, this should set configVersion.
        $result = $this->acumulusContainer->getConfigUpgrade()->upgrade($currentDataModelVersion) && $result;
        if ($result) {
            // Delete setting 'acumulus_siel_datamodel_version' without
            // reverting other settings.
            $setting = $this->registry->model_setting_setting->getSetting('acumulus_siel');
            unset($setting['acumulus_siel_datamodel_version']);
            $this->registry->model_setting_setting->editSetting('acumulus_siel', $setting);
        }

        $this->acumulusContainer->getLog()->info('%s: updated to version = %s, $result = %s', __METHOD__, Version, $result ? 'true' : 'false');
        return $result;
    }

    /**
     * Installs our events.
     *
     * This will add them to the table 'event' from where they are registered on
     * the start of each request. The controller actions can be found in the
     * catalog controller for the catalog events and the admin controller for
     * the admin events.
     *
     * To support updating, this will also be called by the index function.
     * Therefore, we will first remove any existing events from our module.
     *
     * To support other plugins, notably quick_status_updater, we do not only
     * look at the checkout/order events at the catalog side, but at all
     * addOrder and addOrderHistory events.
     *
     * @throws \Exception
     */
    protected function installEvents()
    {
        $this->uninstallEvents();
        $location = $this->getLocation();
        /** @var \ModelSettingEvent $model */
        $model = $this->registry->getModel('setting/event');
        $model->addEvent('acumulus','catalog/model/*/addOrder/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','catalog/model/*/addOrderHistory/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','admin/model/*/addOrder/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','admin/model/*/addOrderHistory/after',$location . '/eventOrderUpdate');
        $model->addEvent('acumulus','admin/view/common/column_left/before',$location . '/eventViewColumnLeft');
        $model->addEvent('acumulus','admin/controller/sale/order/info/before',$location . '/eventControllerSaleOrderInfo');
        $model->addEvent('acumulus','admin/view/sale/order_info/before',$location . '/eventViewSaleOrderInfo');
    }

    /**
     * Removes the Acumulus event handlers from the event table.
     *
     * @throws \Exception
     */
    protected function uninstallEvents()
    {
        /** @var \ModelSettingEvent $model */
        $model = $this->registry->getModel('setting/event');
        $model->deleteEventByCode('acumulus');
    }
}
