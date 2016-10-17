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
        $this->registry->response->setOutput($this->registry->load->view('module/acumulus_form.tpl', $this->data));
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
     * catalog controller.
     *
     * To support updating, this will also be called by the index function.
     * Therefore we will first remove any existing events from our module.
     */
    protected function installEvents()
    {
        $this->uninstallEvents();
        $this->registry->model_extension_event->addEvent('acumulus', 'catalog/model/checkout/order/addOrder/after', 'module/acumulus/eventOrderUpdate');
        $this->registry->model_extension_event->addEvent('acumulus', 'catalog/model/checkout/order/addOrderHistory/after', 'module/acumulus/eventOrderUpdate');
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
