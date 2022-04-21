<?php
namespace Siel\Acumulus\Joomla\Config;

use JRoute;
use JUri;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines common Joomla capabilities for web shops running on Joomla.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'config':
                return JRoute::_('index.php?option=com_acumulus&task=config');
            case 'advanced':
                return JRoute::_('index.php?option=com_acumulus&task=advanced');
            case 'batch':
                return JRoute::_('index.php?option=com_acumulus&task=batch');
            case 'register':
                return JRoute::_('index.php?option=com_acumulus&task=register');
            case 'invoice':
                return JRoute::_('index.php?option=com_acumulus&task=invoice');
            case 'logo':
                return JURI::root(true) . '/administrator/components/com_acumulus/media/siel-logo.svg';
        }
        return parent::getLink($linkType);
    }
}
