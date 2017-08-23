<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

/**
 * OcHelper contains functionality shared between the OC1 and OC2 controllers
 * and models, for both admin and catalog.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * Uninstall function, called when the module is uninstalled by an admin.
     */
    public function uninstall()
    {
        // "Disable" (delete) events, regardless the confirmation answer.
        $this->uninstallEvents();
        parent::uninstall();
    }

    /**
     * Adds our menu-items to the admin menu.
     *
     * @param array $menus
     *   The menus part of the data as will be passed to the view.
     */
    public function eventViewColumnLeft(&$menus) {
        foreach ($menus as &$menu) {
            if ($menu['id'] === 'menu-sale') {
                $menu['children'][] = array(
                    'name' => 'Acumulus',
                    'href' => '',
                    'children' => array(
                        array(
                            'name' => $this->t('batch_form_link_text'),
                            'href' => $this->container->getShopCapabilities()->getLink('batch'),
                            'children' => array(),
                        ),
                        array(
                            'name' => $this->t('advanced_form_link_text'),
                            'href' => $this->container->getShopCapabilities()->getLink('advanced'),
                            'children' => array(),
                        ),
                    ),
                );
            }
        }
    }

    /**
     * Performs the common tasks when displaying a form.
     *
     * @param string $task
     */
    protected function displayFormCommon($task)
    {
        parent::displayFormCommon($task);

        // Render the common parts.
        $this->data['header'] = $this->registry->load->controller('common/header');
        $this->data['column_left'] = $this->registry->load->controller('common/column_left');
        $this->data['footer'] = $this->registry->load->controller('common/footer');
    }

    /**
     * Performs the common tasks when processing and rendering a form.
     *
     * @param string $task
     * @param string $button
     */
    protected function renderFormCommon($task, $button)
    {
        parent::renderFormCommon($task, $button);

        // Send the output.
        $this->registry->response->setOutput($this->registry->load->view($this->registry->getLocation() . '_form.tpl', $this->data));
    }

    /**
     * Checks requirements and installs tables for this module.
     *
     * @return bool
     *   Success.
     */
    protected function doInstall()
    {
        $result = parent::doInstall();

        // Install events
        if (empty($this->data['error_messages'])) {
            $this->installEvents();
        }

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
     * Therefore we will first remove any existing events from our module.
     */
    protected function installEvents()
    {
        $this->uninstallEvents();
        $this->registry->model_extension_event->addEvent('acumulus', 'catalog/model/checkout/order/addOrder/after', $this->registry->getLocation() . '/eventOrderUpdate');
        $this->registry->model_extension_event->addEvent('acumulus', 'catalog/model/checkout/order/addOrderHistory/after', $this->registry->getLocation() . '/eventOrderUpdate');
        $this->registry->model_extension_event->addEvent('acumulus', 'admin/view/common/column_left/before', $this->registry->getLocation() . '/eventViewColumnLeft');
    }

    /**
     * Removes the Acumulus event handlers from the event table.
     */
    protected function uninstallEvents()
    {
        $this->registry->load->model('extension/event');
        $this->registry->model_extension_event->deleteEvent('acumulus');
    }
}
