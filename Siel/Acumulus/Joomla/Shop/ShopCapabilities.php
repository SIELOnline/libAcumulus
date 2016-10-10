<?php
namespace Siel\Acumulus\Joomla\Shop;

use JRoute;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines Joiomla webshops specific capabilities.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        switch ($formType) {
            case 'config':
                return JRoute::_('index.php?option=com_acumulus&task=config');
            case 'advanced':
                return JRoute::_('index.php?option=com_acumulus&task=advanced');
            case 'batch':
                return JRoute::_('index.php?option=com_acumulus&task=batch');
        }
        return parent::getLink($formType);
    }
}
