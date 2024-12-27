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
    public function getLink(string $linkType, mixed $parameter = null): string
    {
        return match ($linkType) {
            'register', 'activate', 'settings', 'mappings', 'batch', 'invoice' => Route::_("index.php?option=com_acumulus&task=$linkType"),
            'logo' => Uri::root(true) . '/administrator/components/com_acumulus/media/siel-logo.svg',
            default => parent::getLink($linkType, $parameter),
        };
    }
}
