<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart23\Helpers;

use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

/**
 * OcHelper contains OpenCart23 specific functionality.
 */
class OcHelper extends BaseOcHelper
{
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
