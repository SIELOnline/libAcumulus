<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

use function in_array;

/**
 * OC3 specific OcHelper methods.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * Returns the location of the module.
     *
     * @return string
     *   The location of the module.
     */
    public function getLocation(): string
    {
        return $this->registry->getLocation();
    }

    /**
     * {@inheritDoc}
     */
    protected function initFormFullPage(string $type): void
    {
        $this->initFormCommon($type);

        $this->registry->document->addStyle('view/stylesheet/acumulus.css');

        $this->data['header'] = $this->registry->load->controller('common/header');
        $this->data['column_left'] = $this->registry->load->controller('common/column_left');
        $this->data['footer'] = $this->registry->load->controller('common/footer');

        // Set headers and titles.
        $this->registry->document->setTitle($this->t("{$type}_form_title"));
        $this->data['page_title'] = $this->t("{$type}_form_title");
        $this->data['text_edit'] = $this->t("{$type}_form_header");

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
        if ($type === 'batch') {
            $this->data['button_icon'] = 'fa-envelope-o';
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
     * {@inheritDoc}
     */
    public function eventControllerSaleOrderInfo(): void
    {
        if ($this->acumulusContainer->getConfig()->getInvoiceStatusSettings()['showInvoiceStatus']) {
            $this->registry->document->addStyle('view/stylesheet/acumulus.css');
            $this->registry->document->addScript('view/javascript/acumulus/acumulus-ajax.js');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function installEvents(): void
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
}
