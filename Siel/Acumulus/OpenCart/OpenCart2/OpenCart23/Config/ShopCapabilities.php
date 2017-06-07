<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart23\Config;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\OpenCart2\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the OpenCart 2.3(+) specific capabilities that differ from OpenCart 2
 * and 2.2.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    protected function paymentMethodToOptions(array $extensions)
    {
        $results = array();
        foreach ($extensions as $extension) {
            Registry::getInstance()->language->load('extension/payment/' . $extension);
            $results[$extension] = Registry::getInstance()->language->get('heading_title');
        }
        return $results;
    }
}
