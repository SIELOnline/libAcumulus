<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

use function in_array;

/**
 * OC4 specific OcHelper methods.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * Returns the location of the module.
     *
     * @return string
     *   The route of the module's controller.
     */
    public function getExtensionRoute(string $extension = 'acumulus'): string
    {
        return $this->registry->getExtensionRoute($extension);
    }

    /**
     * {@inheritDoc}
     */
    protected function initFormFullPage(string $type): void
    {
        $this->initFormCommon($type);

        $this->registry->document->addStyle($this->registry->getExtensionFileUrl('view/stylesheet/acumulus.css'));

        $this->data['header'] = $this->registry->load->controller('common/header');
        $this->data['column_left'] = $this->registry->load->controller('common/column_left');
        $this->data['footer'] = $this->registry->load->controller('common/footer');

        // Set headers and titles.
        $this->registry->document->setTitle($this->t("{$type}_form_title"));
        $this->data['page_title'] = $this->t("{$type}_form_title");
        $this->data['text_form'] = $this->t("{$type}_form_header");

        // Set up breadcrumb.
        $action = $type === 'config' ? '' : $type;
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
            'href' => $this->registry->getExtensionPageUrl($action),
            'separator' => ' :: '
        ];

        // Set the action buttons (action + text).
        $this->data['action'] = $this->registry->getExtensionPageUrl($action);
        if ($type === 'batch') {
            $this->data['button_icon'] = 'fa-envelope';
        } elseif ($type === 'uninstall') {
            $this->data['button_icon'] = 'fa-delete';
        } elseif (in_array($type, ['activate', 'register'])) {
            $this->data['button_icon'] = 'fa-plus';
        } else {
            $this->data['button_icon'] = 'fa-save';
        }
        $this->data['button_save'] = $this->t("button_submit_$type");
        $this->data['cancel'] = $this->registry->getLink('common/dashboard');
        $this->data['button_cancel'] = $type === 'uninstall' ? $this->t('button_cancel_uninstall') : $this->t('button_cancel');
    }

    /**
     * {@inheritDoc}
     */
    protected function outputForm(bool $return = false): ?string
    {
        // Pass messages to twig template.
        /** @var \Siel\Acumulus\Helpers\Form $form */
        $form = $this->data['form'];
        $this->addMessages($form->getMessages());

        // Create the path to the view: route plus part of template name after
        // 'acumulus'.
        $route = $this->getExtensionRoute();
        if ($form->getType() === 'invoice') {
            $route .= '_invoice';
        }
        $route .= '_form';
        $output = $this->registry->load->view($route, $this->data);
        if ($form->getType() === 'invoice') {
            $output = str_replace('acumulus-links', 'acumulus-links col-sm-10', $output);
        }
        // Send or return the output.
        if ($return) {
            return $output;
        } else {
            $this->registry->response->setOutput($output);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function eventControllerSaleOrderInfo(): void
    {
        if ($this->acumulusContainer->getConfig()->getInvoiceStatusSettings()['showInvoiceStatus']) {
            $this->registry->document->addStyle($this->registry->getExtensionFileUrl('view/stylesheet/acumulus.css'));
            $this->registry->document->addScript($this->registry->getExtensionFileUrl('view/javascript/acumulus-ajax.js'));
        }
    }
    /**
     * {@inheritDoc}
     */
    protected function installEvents(): void
    {
        $this->uninstallEvents();
        // @todo: make them less psecific to catch events from other plugins as well, see phpdoc on parent.
        $this->addEvent('acumulus','catalog/model/checkout/order/addOrder/after','eventOrderUpdate');
        $this->addEvent('acumulus','catalog/model/checkout/order/addHistory/after','eventOrderUpdate');
        $this->addEvent('acumulus','admin/view/common/column_left/before','eventViewColumnLeft');
        $this->addEvent('acumulus','admin/controller/sale/order|info/before','eventControllerSaleOrderInfo');
        $this->addEvent('acumulus','admin/view/sale/order_info/before','eventViewSaleOrderInfo');
    }

    protected function addEvent(string $code, string $trigger, string $method, bool $status = true, int $sort_order = 1): void
    {
        $controller = $this->registry->getExtensionRoute($code);
        /** @var \Opencart\Admin\Model\Setting\Event $model */
        $model = $this->registry->getModel('setting/event');
        $model->addEvent([
            'code' => $code,
            'description' => '',
            'trigger' => $trigger,
            'action' => "$controller|$method",
            'status' => $status,
            'sort_order' => $sort_order,
        ]);
    }
}
