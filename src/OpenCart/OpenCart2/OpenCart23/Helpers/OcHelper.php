<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart23\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

/**
 * OcHelper contains OpenCart23 specific functionality.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * Returns the intermediate breadcrumb for the config screen.
     *
     * The config screen is normally accessed via the extensions part of
     * OpenCart. Therefore an intermediate level is added to the breadcrumb,
     * consisting of the extensions page.
     *
     * @return array
     *   The intermediate breadcrumb for the config screen.
     */
    protected function getExtensionsBreadcrumb()
    {
        return array(
            'text' => $this->t('extensions'),
            'href' => Registry::getInstance()->getLink('extension/extension'),
            'separator' => ' :: '
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getRedirectUrl()
    {
        return 'extension/extension';
    }

    /**
     * {@inheritdoc}
     *
     * OC23's deleteEvent method deletes events by code, not id.
     */
    protected function uninstallEvents()
    {
        $this->registry->getEventModel()->deleteEvent('acumulus');
    }
}
