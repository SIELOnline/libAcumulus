<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart20\Helpers;

use Siel\Acumulus\OpenCart\OpenCart2\Helpers\OcHelper as BaseOcHelper;

/**
 * OcHelper contains functionality shared between the OC1 and OC2 controllers
 * and models, for both admin and catalog.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * Installs our events.
     *
     * This will add them to the table 'event' from where they are registered on
     * the start of each request. The controller actions can be found in the
     * catalog controller for the catalog events and the amdin controller for
     * the admin events.
     *
     * To support updating, this will also be called by the index function.
     * Therefore we will first remove any existing events from our module.
     */
    protected function installEvents()
    {
        $this->uninstallEvents();
        $this->registry->model_extension_event->addEvent('acumulus', 'post.order.add', 'module/acumulus/eventOrderUpdate');
        $this->registry->model_extension_event->addEvent('acumulus', 'post.order.history.add', 'module/acumulus/eventOrderUpdate');
    }
}
