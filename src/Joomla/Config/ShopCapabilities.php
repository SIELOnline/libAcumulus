<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines common Joomla capabilities for web shops running on Joomla.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'config':
            case 'advanced':
            case 'batch':
            case 'invoice':
                return Route::_("index.php?option=com_acumulus&task=$linkType");
            case 'logo':
                return Uri::root(true) . '/administrator/components/com_acumulus/media/siel-logo.svg';
        }
        return parent::getLink($linkType);
    }

    public function usesNewCode(): bool
    {
        return true;
    }
}
