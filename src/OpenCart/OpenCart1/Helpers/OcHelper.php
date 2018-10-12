<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

/**
 * OcHelper contains OpenCart1 specific functionality.
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
            'text' => $this->t('modules'),
            'href' => Registry::getInstance()->getLink('extension/module'),
            'separator' => ' :: '
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getRedirectUrl()
    {
        return 'extension/module';
    }

    /**
     * {@inheritdoc}
     *
     * In OC1 this is done in the controller, so this is an empty override.
     */
    protected function displayCommonParts()
    {
    }

    /**
     * {@inheritdoc}
     *
     * In OC1 this is done in the controller, so this is an empty override.
     */
    protected function setOutput()
    {
    }

    /**
     * {@inheritdoc}
     *
     * Oc1 does not know events, so this is an empty override.
     */
    protected function installEvents()
    {
    }

    /**
     * {@inheritdoc}
     *
     * Oc1 does not know events, so this is an empty override.
     */
    protected function uninstallEvents()
    {
    }
}
