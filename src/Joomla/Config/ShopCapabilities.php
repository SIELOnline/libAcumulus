<?php
namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
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
                return Route::_('index.php?option=com_acumulus&task=config');
            case 'advanced':
                return Route::_('index.php?option=com_acumulus&task=advanced');
            case 'batch':
                return Route::_('index.php?option=com_acumulus&task=batch');
            case 'register':
                return Route::_('index.php?option=com_acumulus&task=register');
            case 'invoice':
                return Route::_('index.php?option=com_acumulus&task=invoice');
            case 'logo':
                return URI::root(true) . '/administrator/components/com_acumulus/media/siel-logo.svg';
        }
        return parent::getLink($linkType);
    }
}
